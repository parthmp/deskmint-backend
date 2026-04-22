<?php

namespace App\Modules\InvoiceGeneration;

use App\Mail\SendInvoice;
use App\Models\Invoice;
use App\Models\SettingsSection;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

class InvoiceGenerator{

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
	private SettingsSection $invoice_content;
	
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
	
	/**
	 * stream function
	 *
	 * @return mixed
	 */
	public function stream() : mixed {

		if($this->filename && Storage::disk($this->disk)->exists($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename)) {
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

		if($this->filename && Storage::disk($this->disk)->exists($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename)) {
			return Storage::disk($this->disk)->download($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename);
		}
		
		// Fallback to streaming from memory
		return $this->pdf_object->download($this->filename);

	}

	/**
	 * sendEmail function
	 *
	 * @return void
	 */
	public function sendEmail() : void {

		$email_json = json_decode($this->invoice_content->settings_json);

		Mail::to($this->invoice_data->client_wt->email)->send(new SendInvoice([
			'disk'		=>	$this->disk,
			'path'		=>  $this->invoice_id.DIRECTORY_SEPARATOR.$this->filename,
			'content'	=>	$email_json->email_content_invoice
		]));
	}

}