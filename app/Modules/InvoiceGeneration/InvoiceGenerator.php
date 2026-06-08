<?php

namespace App\Modules\InvoiceGeneration;

use App\Jobs\SendEmailJob;
use App\Models\Invoice;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Modules\Payment\Payment;
use App\Traits\CustomMailSettings;
use Carbon\Carbon;
use DateTime;
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


class InvoiceGenerator{

	use CustomMailSettings;

	private int $company_id;
	private int $invoice_id;
	private string $contents;
	private InvoiceSettingsResolver $invoice_settings_resolver;
	private InvoiceDBOperations $invoice_db_operations;
	private mixed $pdf_object;
	private int $time_offset_minutes;
	private ?Invoice $invoice_data;
	private string $filename = '';
	private string $disk = 'temp_invoices';
	private array $invoice_content;
	
	/**
	 * __construct function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 */
	public function __construct(int $company_id, int $invoice_id, string $time_offset_minutes){
		$this->company_id = $company_id;
		$this->invoice_id = $invoice_id;
		$this->contents = '';
		$this->invoice_settings_resolver = new InvoiceSettingsResolver($company_id, $this->invoice_id);
		$this->invoice_db_operations = new InvoiceDBOperations($company_id, $this->invoice_id);
		$this->time_offset_minutes = $time_offset_minutes;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $date
	 * @param boolean $show_time
	 * @return string
	 */
	protected function formatDateTime(string $date, bool $show_time = false, $sql_format = false) : string {
		
		$date_obj = Carbon::parse($date);

		if($this->time_offset_minutes < 0){
			$date_obj->subMinutes(abs($this->time_offset_minutes));	
		}else if($this->time_offset_minutes > 0){
			$date_obj->addMinutes(abs($this->time_offset_minutes));	
		}

		if(!$sql_format){
			return $show_time ? $date_obj->format('d-M-Y H:i:s') : $date_obj->format('d-M-Y');
		}

		return $show_time ? $date_obj->format('Y-m-d H:i:s') : $date_obj->format('Y-m-d');

	}

	/**
	 * fetchTemplateContents function
	 *
	 * @return string
	 */
	private function fetchTemplateContents() : string {

		$general_settings = $this->invoice_settings_resolver->fetchGeneral();
		$template_name = strtolower($general_settings['template'].'.html');

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
	 * @return array
	 */
	private function generateContextArrayForRenderer() : array {

		$context = [
			'general_settings'			=>	$this->invoice_settings_resolver->fetchGeneral(),
			'client_details_settings'	=>	$this->invoice_settings_resolver->fetchClientDetails(),
			'company_details_settings'	=>	$this->invoice_settings_resolver->fetchCompanyDetails(),
			'additional_company_fields'	=>	$this->invoice_db_operations->fetchAdditionalCompanyFields(),
			'invoice_content_settings'	=>	$this->invoice_db_operations->fetchEmailContentSettings(),
			'company_address_settings'	=>	$this->invoice_settings_resolver->fetchCompanyAddressDetails(),
			'invoice_details_settings'	=>	$this->invoice_settings_resolver->fetchInvoiceDetails(),
			'invoice_data'				=>	$this->invoice_db_operations->fetchInvoiceRow(),
			'total_fields_settings'		=>	$this->invoice_settings_resolver->fetchTotalFieldsDetails()
		];

		
		$context['client_custom_fields_values'] = $this->invoice_db_operations->fetchCustomFieldValuesOfClient((int) $context['invoice_data']['client_id']);
		$context['invoice_custom_fields_values'] = $this->invoice_db_operations->fetchCustomFieldValuesOfInvoice();

		$context['product_rows_data'] = $this->invoice_settings_resolver->fetchProductRowsSettings($context['invoice_data'], (int) $this->company_id);
		$context['invoice_items'] = $this->invoice_db_operations->fetchInvoiceItems();

		$this->invoice_data = $context['invoice_data'];
		$this->invoice_content = $context['invoice_content_settings'];
		
		return $context;
	}

	/**
	 * modifyInvoiceTemplate function
	 *
	 * @return self
	 */
	public function modifyInvoiceTemplate() : self {
		$this->contents = $this->fetchTemplateContents();
		$renderer = new InvoiceRenderer($this->contents, $this->generateContextArrayForRenderer());
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

		$filename = $this->formatDateTime($this->invoice_data->created_at, true, true);
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

	public function generateEInvoice() : self {
		
		$created_at = $this->formatDateTime($this->invoice_data->created_at, false, true);
		$due_date = Carbon::create($this->invoice_data->due_date);
		$due_date = $due_date->format('Y-m-d');

		// Create PEPPOL invoice instance
		$inv = new EInvoice(Presets\Peppol::class);
		$inv->setNumber($this->invoice_data->invoice_number)
			->setIssueDate(new DateTime($created_at))
			->setDueDate(new DateTime($due_date));

		// Set seller
		$seller = new Party();
		$seller->setElectronicAddress(new Identifier('9482348239847239874', '0088'))
			->setCompanyId(new Identifier('AH88726', '0183'))
			->setName('Seller Name Ltd.')
			->setTradingName('Seller Name')
			->setVatNumber('ESA00000000')
			->setAddress(['Fake Street 123', 'Apartment Block 2B'])
			->setCity('Springfield')
			->setCountry('DE');
		$inv->setSeller($seller);

		// Set buyer
		$buyer = new Party();
		$buyer->setElectronicAddress(new Identifier('ES12345', '0002'))
			->setName('Buyer Name Ltd.')
			->setCountry('FR');
		$inv->setBuyer($buyer);

		// Add a product line
		$line = new InvoiceLine();
		$line->setName('Product Name')
			->setPrice(100)
			->setVatRate(16)
			->setQuantity(1);
		$inv->addLine($line);

		$writer = new UblWriter();
		$xml = $writer->export($inv);
		$disk = Storage::disk($this->disk);
		$disk->put($this->invoice_id.DIRECTORY_SEPARATOR."test", $xml);
		return $this;

	}

	private function ifInvoiceDataAvailable() : bool {

		if($this->filename && Storage::disk($this->disk)->exists($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename)) {
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

		$email_json = json_decode($this->invoice_content['settings_json']);

		$data = [
			'disk'		=>	$this->disk,
			'path'		=>  $this->invoice_id.DIRECTORY_SEPARATOR.$this->filename,
			'content'	=>	$this->parseEmailContent($email_json->email_content_invoice)
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