<?php

namespace App\Modules\InvoiceGeneration;

use App\Jobs\SendEmailJob;
use App\Models\Invoice;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Payment;
use App\Traits\CustomMailSettings;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use PhpParser\Node\Expr\Cast\Double;

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

			PAYMENT_PAYPAL => new Payment(new PayPal($data['client_id'], $data['app_id'], $data['secret'], $data['mode'], $data['currency'], (float) $data['amount'])),
			//PAYMENT_STRIPE => new Stripe(),
		};

		return $payment->paymentURL();
		
	}

	private function parseEmailContent(string $content) : string {

		$currency = $this->invoice_data->client_wt->currency->code;
		
		$payment_gateway_url = '';
		
		if((int) $this->invoice_data->payment_method === PAYMENT_PAYPAL || (int) $this->invoice_data->payment_method === PAYMENT_STRIPE){
			
			$payment_settings = $this->invoice_db_operations->fetchPaymentSettings((int) $this->invoice_data->payment_method);
			
			if(!$payment_settings){
				throw new Exception("something went wrong");
			}
			
			$payment_settings = json_decode($payment_settings['settings_json'], true);
			$payment_settings['currency'] = $currency;
			$payment_settings['amount'] = $this->invoice_data->total;
			$payment_settings['secret'] = decrypt($payment_settings['secret']);
			
			$payment_gateway_url = $this->generatePaymentURL((int) $this->invoice_data->payment_method, $payment_settings);

			if(!$payment_gateway_url){
				logger('failed to create payment url');
				//send an email to admin/someone here to notify the failure.
				$data = [
					'first_name'			=>	$this->invoice_data->client_wt->first_name,
					'payment_gateway'		=> $this->invoice_data->payment_method === PAYMENT_PAYPAL ? 'PayPal' : 'Stripe'
				];
				SendEmailJob::dispatch(
					to: $this->invoice_data->client_wt->email,
					to_name: $this->invoice_data->client_wt->first_name.' '.$this->invoice_data->client_wt->last_name,
					mailable_class: \App\Mail\SendFailedPaymentURLGenerationEmail::class,
					mailable_data: [$data],
					smtp: $this->smtpSettings()
				);
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

		$content = str_ireplace($search, $replace, $content);
		$content = str_ireplace('{$invoice_total}', $this->invoice_data->total.' '.$currency, $content);
		$content = str_ireplace('{$unpaid_balance}', $this->invoice_data->balance_due.' '.$currency, $content);
		
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