<?php

namespace App\Modules\InvoiceGeneration;

use App\Helpers\General;
use App\Jobs\SendEmailJob;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Modules\Payment\Payment;
use App\Traits\CustomMailSettings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
use Illuminate\Support\Facades\URL;

class InvoiceGenerator{

	use CustomMailSettings;

	private int $company_id;
	private int $invoice_id;
	private string $contents = '';
	private mixed $pdf_object;
	protected int $time_offset_minutes;
	private Invoice $live_invoice_data;
	private string $filename = '';
	private string $disk = INVOICES_DISK;
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
		$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($this->company_id)->setInvoiceId($this->invoice_id)->execRequiredSettings();
		$this->generateContextArrayForRenderer();
		return $this;
	}


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
		
		// $this->invoice_content = $this->invoice_db_operations->fetchEmailContentSettings();
		// if(!isset($this->invoice_content['settings_json'])){
		// 	$this->invoice_content['settings_json'] = $this->invoice_content;
		// }
		$this->live_invoice_data = $this->invoice_db_operations->fetchInvoiceRowObj();
		$this->data = $this->invoice_db_operations->fetchInvoiceSnapshot($this->invoice_id);
		
	}

	/**
	 * modifyInvoiceTemplate function
	 *
	 * @return self
	 */
	public function modifyInvoiceTemplate() : self {
		$this->contents = $this->fetchTemplateContents();

		$total = BigDecimal::of($this->live_invoice_data->total);
		$balance_due = BigDecimal::of($this->live_invoice_data->balance_due);

		$paid_to_date = $total->minus($balance_due)->toScale(2, RoundingMode::HalfUp)->__toString();

		$renderer = new InvoiceRenderer($this->contents, $this->data, ['balance_due' => $this->live_invoice_data->balance_due, 'paid_to_date' => $paid_to_date, 'status' => $this->live_invoice_data->status]);
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

	private function deleteFiles() : void {

		$disk = Storage::disk($this->disk);

		$current_pdf = $this->invoice_id.DIRECTORY_SEPARATOR.$this->filename.'.pdf';
		$current_xml = $this->invoice_id.DIRECTORY_SEPARATOR.$this->filename.'.xml';

		$current_files = [$current_pdf, $current_xml];

		$files_to_delete = collect($disk->files($this->invoice_id))
			->reject(fn($path) => in_array($path, $current_files))
			->values()
			->all();

		$disk->delete($files_to_delete);

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

		$filename = (string) str_ireplace([' ', ':', '-'], '_', $filename);
		$this->filename = $filename;

		if($save){

			$disk = Storage::disk($this->disk);
			$saved_pdf = false;
			try{

				$result = $disk->put($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename.'.pdf', $this->pdf_object->output());
				$saved_pdf = $result !== false;
			}catch(Exception $e){
				Log::error('PDF save failed for invoice ' . $this->invoice_id . ': ' . $e->getMessage());
			}

			if($saved_pdf){
				$this->deleteFiles();
			}
			
		}

		return $this;
	}

	/**
	 * generateEInvoice function
	 *
	 * @return self
	 */
	public function generateEInvoice() : mixed {
		
		$created_at = General::formatDateTime($this->live_invoice_data->created_at, $this->data['meta']['timezone_offset_minutes'], false, true);
		$due_date = Carbon::create($this->live_invoice_data->due_date);
		$due_date = $due_date->format('Y-m-d');

		
		// Create PEPPOL invoice instance
		$inv = new EInvoice(Presets\Peppol::class);
		$inv->setNumber($this->live_invoice_data->invoice_number)
			->setIssueDate(new DateTime($created_at))
			->setDueDate(new DateTime($due_date))
			->setCurrency($this->data['meta']['currency']);

		// Set seller
		$seller = new Party();
		$company = $this->data['meta']['company'];
		if(trim($company['address_identifier']) !== '' && trim($company['address_scheme']) !== ''){
			$seller = $seller->setElectronicAddress(new Identifier($company['address_identifier'], $company['address_scheme']));
		}

		if(trim($company['company_identifier']) !== '' && trim($company['scheme']) !== ''){
			$seller = $seller->setCompanyId(new Identifier($company['company_identifier'], $company['scheme']));
		}

		$seller = $seller->setName($company['company_name']);

		if($company['gst_vat_number'] !== ''){
			$seller = $seller->setVatNumber($company['gst_vat_number']);
		}

		$seller_company_address = [];

		if($company['apt'] !== ''){
			$seller_company_address[] = $company['apt'];
		}

		if($company['address_street'] !== ''){
			$seller_company_address[] = $company['address_street'];
		}

		if(!empty($seller_company_address)){
			$seller = $seller->setAddress($seller_company_address);
		}

		if($company['city'] !== ''){
			$seller = $seller->setCity($company['city']);
		}

		if($company['postal_code'] !== ''){
			$seller = $seller->setPostalCode($company['postal_code']);
		}


		if($company['country_id']){
			$seller = $seller->setCountry($company['country']['country_code']);
		}
		

		$inv->setSeller($seller);

		// Set buyer
		$buyer = new Party();
		$client = $this->data['meta']['client']['client'];

		if($client['peppol_identifier'] !== '' && $client['peppol_scheme'] !== ''){
			$buyer = $buyer->setElectronicAddress(new Identifier($client['peppol_identifier'], $client['peppol_scheme']));
		}

		$buyer_name = ($client['client_company_name'] !== '') ? $client['client_company_name'] : $client['first_name'].' '.$client['last_name'];

		$buyer = $buyer->setName($buyer_name)
						->setCountry($client['billing_country']['country_code'])
						->setPostalCode($client['billing_postal_code'])
						->setAddress([$client['billing_apt'], $client['billing_street']])
						->setCity($client['billing_city']);

		$inv->setBuyer($buyer);
		
		// Add a product line
		foreach($this->data['meta']['invoice_items'] as $item){

			$line = new InvoiceLine();
			$line = $line->setName($item['product']['product_name'])->setPrice($item['unit_price'])->setQuantity($item['quantity']);
			
			$allowance = new AllowanceOrCharge();
			$allowance->setReason('Discount')->setAmount($item['discount_amount']); // the calculated discount amount
			$line->addAllowance($allowance);

			$vat_rate = BigDecimal::of(0);

			//for normal tax field.
			foreach($this->data['meta']['product_rows_data'] as $row){
				
				if($row['type'] === 'normal'){

					$mapped = $row['mapped'];

					if($mapped[0] === 'tax'){

						$vat_rate = $vat_rate->plus($item['tax']);
						break;

					}

				}
			}

			foreach($this->data['meta']['product_rows_data'] as $row){
				
				if($row['type'] !== 'normal'){

					if((bool) $row['tax']){

						$id_column = (int) $row['id_column'];
						if(isset($item['custom_field_values'])){

							foreach($item['custom_field_values'] as $custom_field){
								if((int) $custom_field['apc_field_id'] === $id_column){
									$vat_rate = $vat_rate->plus($custom_field['value']);
								}
							}

						}
						

					}
				}
			}

			$line->setVatRate($vat_rate->toScale(2, RoundingMode::HalfUp)->__toString());
			$inv->addLine($line);
			
		}

		$allowance = new AllowanceOrCharge();
		$allowance->setReason('Global Discount')->setAmount($this->data['meta']['invoice']['discount_amount_post_tax']);
		$inv->addAllowance($allowance);

		$writer = new UblWriter();
		$xml = $writer->export($inv);
		$disk = Storage::disk($this->disk);
		
		$saved_xml = false;
		try{

			$result = $disk->put($this->invoice_id.DIRECTORY_SEPARATOR.$this->filename.'.xml', $xml);
			$saved_xml = $result !== false;
		}catch(Exception $e){
			Log::error('XML save failed for invoice ' . $this->invoice_id . ': ' . $e->getMessage());
		}

		if($saved_xml){
			$this->deleteFiles();
		}
		
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
	 * getFilename function
	 *
	 * @return string
	 */
	public function getFilename() : string {
		return $this->filename;
	}

	

}