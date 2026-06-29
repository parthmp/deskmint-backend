<?php

namespace App\Services\Invoice;

use App\Helpers\Sanitize;
use App\Models\Invoice;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Modules\CustomFields\CustomFields;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\HandleInvoiceNumbers;
use App\Services\Product\ProductFieldService;
use Illuminate\Http\Request;
use App\Models\InvoiceSnapshot;
use App\Modules\InvoiceGeneration\InvoiceSnapshot as Snapshot;
use App\Repositories\Client\ClientRepository;

class InvoiceBaseService{

	public function __construct(
		private ProductFieldService $product_field_service,
		private InvoiceRepository $invoice_repository,
		private InvoiceNumberService $invoice_number_service,
		private InvoiceCalculationService $invoice_calculation_service,
		private CustomFields $custom_fields,
		private ProductRepository $product_repository,
		private InvoiceSettingsService $invoice_settings_service,
		private ClientRepository $client_repository
	){}

	/**
	 * insertProductRows function
	 *
	 * @param Request $request
	 * @param integer $invoice_id
	 * @param integer $company_id
	 * @return void
	 */
	public function upsertCustomProductRows(Request $request, int $invoice_id, int $company_id) : void {
		$this->product_field_service->upsertCustomProductRows($request, $invoice_id, $company_id);
	}

	/**
	 * upsertInvoice function
	 *
	 * @param Request $request
	 * @param array $data
	 * @param integer $invoice_id
	 * @return Invoice
	 */
	public function upsertInvoice(Request $request, array $data, int $invoice_id = 0) : Invoice {

		$data = $this->invoice_repository->upsertInvoiceData($request, $data, $invoice_id);
		
		$invoice = $data['invoice'];
		$rows = $data['rows'];

		$invoice_items = [];
		foreach($rows as $row){

			$temp = [];

			$temp['row_uuid'] = Sanitize::input($row['row_uuid']);
			$temp['invoice_id'] = $invoice->id;
			$temp['product_id'] = Sanitize::input($row['product_id']);
			$temp['description'] = Sanitize::input($row['description'] ?? '');
			$temp['unit_price'] = Sanitize::input($row['unit_price'] ?? 0);

			$temp['discount'] = Sanitize::input($row['discount'] ?? 0);
			$temp['discount_amount'] = Sanitize::input($row['discount_amount'] ?? 0);

			$temp['quantity'] = Sanitize::input($row['quantity'] ?? 1);
			$temp['tax'] = Sanitize::input($row['tax'] ?? 0);
			$temp['tax_amount'] = Sanitize::input($row['tax_amount'] ?? 0);
			$temp['line_subtotal'] = Sanitize::input($row['line_subtotal'] ?? 0);
			$temp['line_total'] = Sanitize::input($row['line_total'] ?? 0);

			$invoice_items[] = $temp;
			
		}

		$this->invoice_repository->upsertInvoiceItems($invoice_items, $invoice_id);

		$invoice_items = null;

		return $invoice;

	}

	private function filterValidProductRows(array $product_rows, int $company_id): array {

		if(empty($product_rows)){
			return [];
		}
		
		// Extract all product IDs from request
		$product_ids = [];
		foreach($product_rows as $index => $row){
			if(!empty($row['product_id'])){
				$product_ids[$index] = (int) $row['product_id'];
			}
		}
		
		if(empty($product_ids)){
			return [];
		}
		
		// Check which product IDs exist in database
		$valid_product_ids = $this->product_repository->fetchValidProductIdsByIds($company_id, $product_ids);
		
		// Filter rows - keep only those with valid product IDs
		$filtered_rows = [];
		foreach($product_ids as $index => $product_id){
			if(in_array($product_id, $valid_product_ids, true)){
				$filtered_rows[] = $product_rows[$index];
			}
		}

		$sanitized_rows = [];

		foreach($filtered_rows as $row){
			$temp = [];
			foreach($row as $key => $value){
				$temp[$key] = Sanitize::input($value);
			}
			$sanitized_rows[] = $temp;
		}
		
		return $sanitized_rows;
	}

	/**
	 * sanitizeInvoiceNumber function
	 *
	 * @param string $invoice_number
	 * @return string
	 */
	public function sanitizeInvoiceNumber(string $invoice_number): string {
		return $this->invoice_number_service->sanitizeInvoiceNumber($invoice_number);
	}

	/**
	 * getInvoiceNumber function
	 *
	 * @param string $invoice_number
	 * @param integer $company_id
	 * @param integer $timezone_offset_minutes
	 * @return string
	 */
	public function getInvoiceNumber(string $invoice_number, int $company_id, int $timezone_offset_minutes) : string {
		return $this->invoice_number_service->getInvoiceNumber($invoice_number, $company_id, $timezone_offset_minutes);
	}

	/**
	 * getDiscountNumberAndType function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function getDiscountNumberAndType(Request $request) : array {

		$discount_number = 0;

		if($request->filled('data.invoice_details.global_discount')){
			$discount_number = (float) Sanitize::input($request->input('data.invoice_details.global_discount'));
		}

		$discount_type = 'percentage';

		if($request->filled('data.invoice_details.global_discount_type')){
			$discount_type = Sanitize::input($request->input('data.invoice_details.global_discount_type'));
			if($discount_type !== 'percentage'){
				$discount_type = 'amount';
			}else{
				$discount_number = max(0, min(100, (double) $discount_number));
			}
		}

		return [
			'discount_type'		=>	$discount_type,
			'discount_number'	=>	$discount_number
		];

	}

	/**
	 * calculateInvoice function
	 *
	 * @param array $product_rows
	 * @param string $discount_type
	 * @param string $discount_number
	 * @return array
	 */
	public function calculateInvoice(array $product_rows, string $discount_type, string $discount_number) : array {
		return $this->invoice_calculation_service->calculateInvoice($product_rows, $discount_type, $discount_number);
	}

	/**
	 * getInvoiceInsertData function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function getInvoiceData(Request $request) : array {

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$client_id = Sanitize::input($request->input('data.invoice_details.client.client_id'));
		$invoice_number = Sanitize::input($request->input('data.invoice_details.invoice_number.value')) ?? '';

		$invoice_number = $this->sanitizeInvoiceNumber($invoice_number);

		$invoice_date = Sanitize::input($request->input('data.invoice_details.invoice_date.value'));
		$due_date = Sanitize::input($request->input('data.invoice_details.due_date.value'));

		$timezone_offset_minutes = (int) Sanitize::input($request->input('timezone_offset_minutes'));

		$po_number = '';
		if($request->filled('data.invoice_details.po_number')){
			$po_number = Sanitize::input($request->input('data.invoice_details.po_number'));
		}

		$invoice_terms = '';
		if($request->filled('data.invoice_terms')){
			$invoice_terms = Sanitize::input($request->input('data.invoice_terms') ?? '');
		}

		$send_email = false;
		if($request->filled('settings.send_invoice_in_email')){
			if($request->input('settings.send_invoice_in_email')){
				$send_email = true;
			}
		}

		$payment_method = Sanitize::input($request->input('settings.payment_method'));
		$product_rows = $this->filterValidProductRows($request->input('data.product_rows'), $company_id);

		$timezone_offset_minutes = Sanitize::input($request->input('timezone_offset_minutes'));

		$invoice_number = $this->getInvoiceNumber($invoice_number, $company_id, (int) $timezone_offset_minutes);
		
		$settings = $this->invoice_settings_service->setCompany((int) $company_id);
		$patten_result = (new HandleInvoiceNumbers((int) $company_id, $settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->checkPatternWithSuffix($invoice_number);
		$patten_matched = $patten_result['matched'];
		
		if($patten_matched){
			$scan_chars = strlen((string) $patten_result['suffix']);
		}else{
			$scan_chars = 0; /* no pattern match means user edited invoice number manually */
		}

		$discount_array = $this->getDiscountNumberAndType($request);

		$discount_type = $discount_array['discount_type'];
		$discount_number = $discount_array['discount_number'];
		$totals = $this->calculateInvoice($product_rows, $discount_type, $discount_number);
		$global_total = $totals['global_total'];
		$global_subtotal = $totals['global_subtotal'];
		$global_tax_amount = $totals['global_tax_amount'];
		$global_discount_amount_post_tax = $totals['global_discount_amount_post_tax'];
		$global_discount_amount_pre_tax = $totals['global_discount_amount_pre_tax'];
		$discount_number = $totals['discount_number'];

		$client = $this->client_repository->fetchById($client_id);

		return [
			'settings' 							=>  $settings,
			'client_id'							=>	$client_id,
			'first_name'						=>	$client->first_name,
			'last_name'							=>	$client->last_name,
			'full_name'							=>	$client->first_name.' '.$client->last_name,
			'client_company'					=>	$client->client_company_name,
			'currency_id'						=>	$client->currency_id,
			'company_id'						=>	$company_id,
			'invoice_number'					=>	$invoice_number,
			'invoice_date'						=>	$invoice_date,
			'due_date'							=>	$due_date,
			'po_number'							=>	$po_number,
			'discount_number'					=>	$discount_number,
			'discount_type'						=>	$discount_type,
			'global_discount_amount_post_tax'	=>	$global_discount_amount_post_tax,
			'global_discount_amount_pre_tax'	=>	$global_discount_amount_pre_tax,
			'global_subtotal'					=>	$global_subtotal,
			'global_tax_amount'					=>	$global_tax_amount,
			'global_total'						=>	$global_total,
			'invoice_terms'						=>	$invoice_terms,
			'send_email'						=>	$send_email,
			'payment_method'					=>	$payment_method,
			'patten_matched'					=>	$patten_matched,
			'scan_chars'						=>	$scan_chars,
			'timezone_offset_minutes'			=>	$timezone_offset_minutes,
			'rows'								=>	$totals['rows']
		];
		
	}

	/**
	 * resetManualInvoieNumberResetFlag function
	 *
	 * @param integer $company_id
	 * @return void
	 */
	public function resetManualInvoieNumberResetFlag(int $company_id) : void {
		$this->invoice_number_service->resetManualInvoieNumberResetFlag($company_id);
	}

	/**
	 * saveOrUpdate function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return integer
	 */
	public function saveOrUpdate(Request $request, int $company_id, int $invoice_id = 0) : int {
		
		$invoice = $this->upsertInvoice($request, $this->getInvoiceData($request), $invoice_id); //adds invoice data + non changable (normal) product cols/rows

		$add = true;

		if($invoice_id > 0){
			$add = false;
		}

		$this->custom_fields->upsertCustomFieldValues($request, $invoice->id, InvoicesCustomField::class, InvoiceCustomFieldValue::class, 'invoices_flat', 'invoice', $add);
		
		//to upsert custom product rows/cols
		$this->upsertCustomProductRows($request, $invoice->id, $company_id);

		/* override manual reset here */
		$this->resetManualInvoieNumberResetFlag($company_id);



		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();

		InvoiceSnapshot::updateOrCreate(
			['invoice_id' 	=> $invoice->id],
			['snapshot' 	=> $snapshot]
		);

		return $invoice->id;

	}

}