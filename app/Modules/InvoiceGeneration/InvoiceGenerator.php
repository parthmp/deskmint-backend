<?php

namespace App\Modules\InvoiceGeneration;

use App\Helpers\General;
use App\Jobs\SendEmailJob;
use App\Models\Invoice;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Modules\Payment\Payment;
use App\Traits\CustomMailSettings;
use Carbon\Carbon;
use DateTime;
use Einvoicing\AllowanceOrCharge;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use Einvoicing\Identifier;
use Einvoicing\Invoice as EInvoice;
use Einvoicing\InvoiceLine;
use Einvoicing\Party;
use Einvoicing\Presets;
use Einvoicing\Writers\UblWriter;
use Illuminate\Database\Eloquent\Collection;

class InvoiceGenerator{

	use CustomMailSettings;

	private int $company_id;
	private int $invoice_id;
	private string $contents = '';
	private mixed $pdf_object;
	protected int $time_offset_minutes;
	private ?Invoice $invoice_data;
	private string $filename = '';
	private string $disk = 'temp_invoices';
	private array $invoice_content;
	private array $product_rows_data;
	private ?Collection $invoice_items;
	private array $data;
	
	/**
	 * __construct function
	 *
	 * @param InvoiceDBOperations $invoice_db_operations
	 */
	public function __construct(private InvoiceDBOperations $invoice_db_operations){
	}

	/**
	 * setCompanyId function
	 *
	 * @param integer $company_id
	 * @return self
	 */
	public function setCompanyId(int $company_id) : self {
		$this->company_id = $company_id;
		return $this;
	}

	/**
	 * setInvoiceId function
	 *
	 * @param integer $invoice_id
	 * @return self
	 */
	public function setInvoiceId(int $invoice_id) : self {
		$this->invoice_id = $invoice_id;
		return $this;
	}

	/**
	 * exec function
	 *
	 * @return self
	 */
	public function exec() : self {
		//$this->invoice_settings_resolver = $this->invoice_settings_resolver->setCompanyId($this->company_id)->setInvoiceId($this->invoice_id);
		$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($this->company_id)->setInvoiceId($this->invoice_id)->execRequiredSettings();
		return $this;
	}

	//extracted in General::formatDateTime
	// protected function formatDateTime(string $date, bool $show_time = false, $sql_format = false) : string {
		
	// 	$date_obj = Carbon::parse($date);
		
	// 	if($this->time_offset_minutes < 0){
	// 		$date_obj->subMinutes(abs($this->time_offset_minutes));	
	// 	}else if($this->time_offset_minutes > 0){
	// 		$date_obj->addMinutes(abs($this->time_offset_minutes));	
	// 	}

	// 	if(!$sql_format){
	// 		return $show_time ? $date_obj->format('d-M-Y H:i:s') : $date_obj->format('d-M-Y');
	// 	}

	// 	return $show_time ? $date_obj->format('Y-m-d H:i:s') : $date_obj->format('Y-m-d');

	// }

	/**
	 * fetchTemplateContents function
	 *
	 * @return string
	 */
	private function fetchTemplateContents() : string {

		//$general_settings = $this->invoice_settings_resolver->fetchGeneral();
		$template_name = strtolower($this->data['general']['template'].'.html');

		try{
			return Storage::disk('invoice_templates')->get($template_name);
		}catch(Exception $e){
			Log::error("Unable to read file : {template_name}", ['template_name' => $template_name]);
		}
		return '';
	}

	/**
	 * generateContextArrayForRenderer function
	 *
	 * @return void
	 */
	public function generateContextArrayForRenderer() : void {

		// $context = [
		// 	'general_settings'			=>	$this->invoice_settings_resolver->fetchGeneral(),
		// 	'client_details_settings'	=>	$this->invoice_settings_resolver->fetchClientDetails(),
		// 	'company_details_settings'	=>	$this->invoice_settings_resolver->fetchCompanyDetails(),
		// 	'additional_company_fields'	=>	$this->invoice_db_operations->fetchAdditionalCompanyFields(),
		// 	'invoice_content_settings'	=>	$this->invoice_db_operations->fetchEmailContentSettings(),
		// 	'company_address_settings'	=>	$this->invoice_settings_resolver->fetchCompanyAddressDetails(),
		// 	'invoice_details_settings'	=>	$this->invoice_settings_resolver->fetchInvoiceDetails(),
		// 	'invoice_data'				=>	$this->invoice_db_operations->fetchInvoiceRow(),
		// 	'total_fields_settings'		=>	$this->invoice_settings_resolver->fetchTotalFieldsDetails()
		// ];
		
		// $context['client_custom_fields_values'] = $this->invoice_db_operations->fetchCustomFieldValuesOfClient((int) $context['invoice_data']['client_id']);

		// $context['invoice_custom_fields_values'] = $this->invoice_db_operations->fetchCustomFieldValuesOfInvoice() ?? [];
		
		// $context['product_rows_data'] = $this->invoice_settings_resolver->fetchProductRowsSettings($context['invoice_data']);
		// $context['invoice_items'] = $this->invoice_db_operations->fetchInvoiceItemsWithCustomCols();

		// $this->invoice_data = $context['invoice_data'];
		// $this->invoice_content = $context['invoice_content_settings'];
		// if(!isset($this->invoice_content['settings_json'])){
		// 	$this->invoice_content['settings_json'] = $this->invoice_content;
		// }
		// $this->invoice_items = $context['invoice_items'];
		// $this->product_rows_data = $context['product_rows_data'];
		
		// return $context;

		$this->data = $this->invoice_db_operations->fetchInvoiceSnapshot($this->invoice_id);

	}

	/**
	 * modifyInvoiceTemplate function
	 *
	 * @return self
	 */
	public function modifyInvoiceTemplate() : self {
		$this->contents = $this->fetchTemplateContents();
		$this->generateContextArrayForRenderer();
		$renderer = new InvoiceRenderer($this->contents, $this->data);
		$this->contents = $renderer->render();
		return $this;
	}

	/**
	 * getInvoiceHTML function
	 *
	 * @return string
	 */
	public function getInvoiceHTML() : string {
		return $this->contents;
	}

	/**
	 * generatePDF function
	 *
	 * @param boolean $save
	 * @param boolean $add_random
	 * @return self
	 */
	public function generatePDF(bool $save = false, bool $add_random = false) : self {
		
		$this->modifyInvoiceTemplate();

		$this->pdf_object = App::make('dompdf.wrapper');
		$this->pdf_object->loadHTML($this->contents);

		$filename = General::formatDateTime($this->data['meta']['created_at'], $this->data['meta']['timezone_offset_minutes'], true, true);
		if($add_random){
			$filename .= '_' . uniqid();
		}

		$filename .= '.pdf';
		$filename = (string) str_ireplace([' ', ':', '-'], '_', $filename);
		$this->filename = $filename;

		if($save){

			$disk = Storage::disk($this->disk);
			$disk->put($this->invoice_id.DIRECTORY_SEPARATOR.$filename, $this->pdf_object->output());
			
		}

		return $this;
	}

	/**
	 * generateEInvoice function
	 *
	 * @return self
	 */
	public function generateEInvoice() : self {
		
		//$created_at = $this->formatDateTime($this->invoice_data->created_at, false, true);
		$created_at = General::formatDateTime($this->invoice_data->created_at, $this->time_offset_minutes, false, true);
		$due_date = Carbon::create($this->invoice_data->due_date);
		$due_date = $due_date->format('Y-m-d');

		$company = $this->invoice_db_operations->fetchDefaultCompanyById($this->company_id);

		// Create PEPPOL invoice instance
		$inv = new EInvoice(Presets\Peppol::class);
		$inv->setNumber($this->invoice_data->invoice_number)
			->setIssueDate(new DateTime($created_at))
			->setDueDate(new DateTime($due_date))
			->setCurrency($this->invoice_data->client_wt->currency->code);

		// Set seller
		$seller = new Party();

		if(trim($company->address_identifier) !== '' && trim($company->address_scheme) !== ''){
			$seller = $seller->setElectronicAddress(new Identifier($company->address_identifier, $company->address_scheme));
		}

		if(trim($company->company_identifier) !== '' && trim($company->scheme) !== ''){
			$seller = $seller->setCompanyId(new Identifier($company->company_identifier, $company->scheme));
		}

		$seller = $seller->setName($company->company_name);

		if($company->gst_vat_number !== ''){
			$seller = $seller->setVatNumber($company->gst_vat_number);
		}

		$seller_company_address = [];

		if($company->apt !== ''){
			$seller_company_address[] = $company->apt;
		}

		if($company->address_street !== ''){
			$seller_company_address[] = $company->address_street;
		}

		if(!empty($seller_company_address)){
			$seller = $seller->setAddress($seller_company_address);
		}

		if($company->city !== ''){
			$seller = $seller->setCity($company->city);
		}

		if($company->postal_code !== ''){
			$seller = $seller->setPostalCode($company->postal_code);
		}


		if($company->country_id){
			$seller = $seller->setCountry($company->country->country_code);
		}
		

		$inv->setSeller($seller);

		// Set buyer
		$buyer = new Party();

		if($this->invoice_data->client_wt->peppol_identifier !== '' && $this->invoice_data->client_wt->peppol_scheme !== ''){
			$buyer = $buyer->setElectronicAddress(new Identifier($this->invoice_data->client_wt->peppol_identifier, $this->invoice_data->client_wt->peppol_scheme));
		}

		$buyer_name = ($this->invoice_data->client_wt->client_company_name !== '') ? $this->invoice_data->client_wt->client_company_name : $this->invoice_data->client_wt->first_name.' '.$this->invoice_data->client_wt->last_name;

		$buyer = $buyer->setName($buyer_name)
						->setCountry($this->invoice_data->client_wt->billing_country->country_code)
						->setPostalCode($this->invoice_data->client_wt->billing_postal_code)
						->setAddress([$this->invoice_data->client_wt->billing_apt, $this->invoice_data->client_wt->billing_street])
						->setCity($this->invoice_data->client_wt->billing_city);

		$inv->setBuyer($buyer);
		
		// Add a product line
		foreach($this->invoice_items as $item){

			$line = new InvoiceLine();
			$line = $line->setName($item->product->product_name)->setPrice($item->unit_price)->setQuantity($item->quantity);
			
			$allowance = new AllowanceOrCharge();
			$allowance->setReason('Discount')->setAmount($item->discount_amount); // the calculated discount amount
			$line->addAllowance($allowance);

			$vat_rate = 0;

			foreach($this->product_rows_data as $row){
				
				if($row['type'] === 'normal'){
					$mapped = $row['mapped'];
					if($mapped[0] === 'tax'){
						$vat_rate += (float) $item->tax;
					}

				}else{
					//for custom product row fields.
					if((int) $row['tax'] === 1){

						//for tax fields
						$vat_rate += (float) $row['tax_rate'];

					}
				}

			}

			$line->setVatRate($vat_rate);
			$inv->addLine($line);
			
		}

		$allowance = new AllowanceOrCharge();
		$allowance->setReason('Global Discount')->setAmount($this->invoice_data->discount_amount_post_tax);
		$inv->addAllowance($allowance);

		$writer = new UblWriter();
		$xml = $writer->export($inv);
		$disk = Storage::disk($this->disk);
		$filename = str_ireplace('pdf', 'xml', $this->filename);
		$disk->put($this->invoice_id.DIRECTORY_SEPARATOR.$filename, $xml);
		return $this;

	}

	/**
	 * ifInvoiceDataAvailable function
	 *
	 * @return boolean
	 */
	private function ifInvoiceDataAvailable() : bool {

		if($this->filename && Storage::disk($this->disk)->exists($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename)) {
			return true;
		}

		return false;
	}

	/**
	 * ifEInvoiceAvailable function
	 *
	 * @return boolean
	 */
	private function ifEInvoiceAvailable() : bool {
		$filename = str_ireplace('pdf', 'xml', $this->filename);
		if($filename && Storage::disk($this->disk)->exists($this->invoice_id.DIRECTORY_SEPARATOR.$filename)) {
			return true;
		}

		return false;
	}
	
	/**
	 * stream function
	 *
	 * @return mixed
	 */
	public function stream() : mixed {

		if($this->ifInvoiceDataAvailable()) {
			return response()->file(Storage::disk($this->disk)->path($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename));
		}
		
		return $this->pdf_object->stream();
	}

	/**
	 * download function
	 *
	 * @return mixed
	 */
	public function download() : mixed {

		if($this->ifInvoiceDataAvailable()) {
			return Storage::disk($this->disk)->download($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename);
		}
		
		// Fallback to streaming from memory
		return $this->pdf_object->download($this->filename);

	}

	/**
	 * generatePaymentURL function
	 *
	 * @return string|null
	 */
	private function generatePaymentURL(int $payment_gateway, array $data) : ?string {
		
		$payment = match($payment_gateway){

			PAYMENT_PAYPAL => new Payment(new PayPal($this->invoice_id, $data['client_id'], $data['app_id'], $data['secret'], $data['mode'], $data['currency'], (float) $data['amount'])),
			PAYMENT_STRIPE => new Payment(new Stripe($this->invoice_id, $data['secret'], $data['currency'], (float) $data['amount'])),
		};

		return $payment->paymentURL();
		
	}

	/**
	 * sendUrlGenerationFailedEmail function
	 *
	 * @return void
	 */
	private function sendUrlGenerationFailedEmail() : void {

		$data = [
			'payment_method'		=>  $this->invoice_data->payment_method === PAYMENT_PAYPAL ? 'PayPal' : 'Stripe'
		];

		$info = $this->invoice_db_operations->fetchAdminEmails();

		if($info){

			$first_email = $info[0]['email'];
			$first_name = $info[0]['name'];

			array_shift($info);

			$info = array_values($info);

			SendEmailJob::dispatch(
				to: $first_email,
				to_name: $first_name,
				mailable_class: \App\Mail\SendFailedPaymentURLGenerationEmail::class,
				mailable_data: [$data],
				smtp: $this->smtpSettings(),
				cc: $info
			);
		}

	}

	/**
	 * replaceBetweenTags function
	 *
	 * @param string $text
	 * @param string $starting_tag
	 * @param string $ending_tag
	 * @param string $new_content
	 * @return string
	 */
	private function replaceBetweenTags(string $text, string $starting_tag, string $ending_tag, string $new_content) : string {

		$startPos = strpos($text, $starting_tag);

		if ($startPos === false) return $text;
		
		$endPos = strpos($text, $ending_tag, $startPos + strlen($starting_tag));

		if ($endPos === false) return $text;
		
		$before = substr($text, 0, $startPos + strlen($starting_tag));
		$after = substr($text, $endPos);
		
		return $before.$new_content.$after;

	}

	/**
	 * parseEmailContent function
	 *
	 * @param string $content
	 * @return string
	 */
	private function parseEmailContent(string $content) : string {

		$currency = $this->invoice_data->client_wt->currency->code;
		
		$payment_gateway_url = '';
		
		if((int) $this->invoice_data->payment_method !== PAYMENT_CASH && (int) $this->invoice_data->payment_method !== PAYMENT_NETBANKING){
			
			$payment_settings = $this->invoice_db_operations->fetchPaymentSettings((int) $this->invoice_data->payment_method);
			
			if(!$payment_settings){
				logger('something went wrong with payment settings data -> '.json_encode($payment_settings));
				throw new Exception("something went wrong");
			}
			
			$payment_settings = json_decode($payment_settings['settings_json'], true);
			$payment_settings['currency'] = $currency;
			$payment_settings['amount'] = $this->invoice_data->balance_due;
			$payment_settings['secret'] = decrypt($payment_settings['secret']);
			
			$payment_gateway_url = $this->generatePaymentURL((int) $this->invoice_data->payment_method, $payment_settings);
			
			if(!$payment_gateway_url){
				//send an email to admins to notify the failure.
				$this->sendUrlGenerationFailedEmail();
			}
		}
		

		$search = [
			'{$client_first_name}',
			'{$client_last_name}',
			'{$invoice_date}',
			'{$due_date}',
			'{$invoice_number}',
			'{$payment_url}'
		];

		$replace = [
			$this->invoice_data->client_wt->first_name,
			$this->invoice_data->client_wt->last_name,
			Carbon::parse($this->invoice_data->invoice_date)->format('d-M-Y'),
			Carbon::parse($this->invoice_data->due_date)->format('d-M-Y'),
			$this->invoice_data->invoice_number,
			$payment_gateway_url
		];

		if((int) $this->invoice_data->payment_method === PAYMENT_CASH || (int) $this->invoice_data->payment_method === PAYMENT_NETBANKING){
			$content = $this->replaceBetweenTags($content,  '[{online-payment-start}]', '[{online-payment-end}]', '');
		}

		$content = str_ireplace($search, $replace, $content);
		$content = str_ireplace('{$invoice_total}', $this->invoice_data->total.' '.$currency, $content);
		$content = str_ireplace('{$unpaid_balance}', $this->invoice_data->balance_due.' '.$currency, $content);
		$content = str_ireplace('[{online-payment-start}]', '', $content);
		$content = str_ireplace('[{online-payment-end}]', '', $content);
		
		return $content;

	}

	/**
	 * sendEmail function
	 *
	 * @return void
	 */
	public function sendEmail() : void {
		
		if(!$this->ifInvoiceDataAvailable()){
			Log::alert('Could not send invoice as an attachment. invoice #:'.$this->invoice_id);
			throw new Exception('Could not send invoice as an attachment.');
		}
		
		if(is_array($this->invoice_content['settings_json'])){
			$email_json = json_decode(json_encode($this->invoice_content['settings_json']));
		}else{
			$email_json = json_decode($this->invoice_content['settings_json']);
		}
		

		$attachments = [];

		$attachments[] = ['disk' => $this->disk, 'path' => $this->invoice_id.DIRECTORY_SEPARATOR.$this->filename];

		if($this->ifEInvoiceAvailable()){
			$xml_invoice = str_ireplace('pdf', 'xml', $this->invoice_id.DIRECTORY_SEPARATOR.$this->filename);
			$attachments[] = ['disk' => $this->disk, 'path' => $xml_invoice];
		}


		$data = [
			'attachments'	=>  $attachments,
			'content'		=>	$this->parseEmailContent($email_json->email_content_invoice)
		];

		SendEmailJob::dispatch(
			to: $this->invoice_data->client_wt->email,
			to_name: $this->invoice_data->client_wt->first_name.' '.$this->invoice_data->client_wt->last_name,
			mailable_class: \App\Mail\SendInvoice::class,
			mailable_data: [$data],
			smtp: $this->smtpSettings()
		);
	}

}