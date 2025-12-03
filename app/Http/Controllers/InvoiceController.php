<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Invoice;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoiceItem;
use App\Models\InvoicesCustomField;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\HandleInvoiceNumbers;
use App\Services\Invoice\InvoiceService;
use App\Services\InvoiceSettingsService;
use App\Traits\CustomFieldsPrinting;
use App\Traits\CustomFieldsUpsert;
use App\Traits\CustomFieldsValidation;
use App\Traits\PaymentGatewayDetails;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Brick\Math\RoundingMode;
use Carbon\Carbon;

class InvoiceController extends Controller{

	use CustomFieldsPrinting, PaymentGatewayDetails, CustomFieldsValidation, CustomFieldsUpsert;

	private ClientRepository $client_repository;
	private InvoiceRepository $invoice_repository;
	private ProductRepository $product_repository;
	private InvoiceService $invoice_service;

	public function __construct(ClientRepository $client_repository, InvoiceRepository $invoice_repository, ProductRepository $product_repository, InvoiceService $invoice_service){
		$this->client_repository = $client_repository;
		$this->invoice_repository = $invoice_repository;
		$this->product_repository = $product_repository;
		$this->invoice_service = $invoice_service;
	}
    
	/**
	 * searchClients function
	 *
	 * @param Request $request
	 * @return Collection
	 */
	public function searchClients(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$searched = (string) Sanitize::input($request->input('searched'));

		try{
			return $this->client_repository->searchByName($company_id, $searched);
		}catch(Exception $e){
			return General::wentWrong();
		}
	}

	/**
	 * fetchInitialData function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchInitialData(Request $request){

		$v = Validator::make($request->all(), [
			'timezone_offset_minutes'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validator' => 'invalid_timezone'], config('global.error_code'));
		}

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$timezone_offset_minutes = (int) Sanitize::input($request->input('timezone_offset_minutes'));

		try{
			return $this->invoice_repository->getInitialData($request, $company_id, $timezone_offset_minutes);
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function fetchProducts(Request $request){
		
		$company_id = (int) Sanitize::input($request->input('company_id'));
		$searched = (string) Sanitize::input($request->input('searched'));

		try{	
			return $this->product_repository->searchByName($company_id, $searched);
		}catch(Exception $e){
			return General::wentWrong();
		}

		
	}

	
	/**
	 * store function
	 *
	 * @param Request $request
	 * @return void
	 */
	public function store(Request $request){
		
		$company_id = (int) Sanitize::input($request->input('company_id'));

		$errors = $this->invoice_service->validateAllForInvoice($request, $company_id);

		if($errors !== null){
			return $errors;
		}

		$product_rows = $request->input('data.product_rows');

		$discount_array = $this->invoice_service->getDiscountNumberAndType($request);

		$discount_type = $discount_array['discount_type'];
		$discount_number = $discount_array['discount_number'];

		$totals = $this->invoice_service->calculateInvoice($product_rows, $discount_type, $discount_number);

		$global_total = $totals['global_total'];
		$global_subtotal = $totals['global_subtotal'];
		$global_tax_amount = $totals['global_tax_amount'];
		$global_discount_amount = $totals['global_discount_amount'];
		$rows = $totals['rows'];

		$client_id = Sanitize::input($request->input('data.invoice_details.client.client_id'));
		$invoice_number = Sanitize::input($request->input('data.invoice_details.invoice_number.value')) ?? '';

		$invoice_number = $this->invoice_service->sanitizeInvoiceNumber($invoice_number);

		$invoice_date = Sanitize::input($request->input('data.invoice_details.invoice_date.value'));
		$due_date = Sanitize::input($request->input('data.invoice_details.due_date.value'));

		$po_number = '';
		if($request->filled('data.invoice_details.po_number')){
			$po_number = Sanitize::input($request->input('data.invoice_details.po_number'));
		}

		$invoice_terms = '';
		if($request->filled('data.invoice_details.po_number')){
			$invoice_terms = Sanitize::input($request->input('data.invoice_terms') ?? '');
		}

		$send_email = false;
		if($request->filled('settings.send_invoice_in_email')){
			if($request->input('settings.send_invoice_in_email')){
				$send_email = true;
			}
		}

		$payment_method = Sanitize::input($request->input('settings.payment_method'));

		$timezone_offset_minutes = Sanitize::input($request->input('timezone_offset_minutes'));

		$invoice_number = $this->invoice_service->getInvoiceNumber($invoice_number, $company_id, (int) $timezone_offset_minutes);
		
		$settings = new InvoiceSettingsService((int) $company_id);
		$patten_result = (new HandleInvoiceNumbers((int) $company_id, $settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->checkPatternWithSuffix($invoice_number);
		$patten_matched = $patten_result['matched'];
		
		if($patten_matched){
			$scan_chars = strlen((string) $patten_result['suffix']);
		}else{
			$scan_chars = 0; /* no pattern match means user edited invoice number manually */
		}
		
		$settings_snapshot = $settings->getProductColumns(); /* json text for product_columns settings from SettingsSection table, if it does not exist, it falls back to the default values */

		$invoice = new Invoice();
		$invoice->client_id = $client_id;
		$invoice->company_id = $company_id;
		$invoice->invoice_number = $invoice_number;
		$invoice->invoice_date = Carbon::parse($invoice_date);
		$invoice->due_date = Carbon::parse($due_date);
		$invoice->po_number = $po_number;
		$invoice->discount = $discount_number;
		$invoice->discount_type = ($discount_type === 'percentage') ? DISCOUNT_TYPE_PERCENTAGE : DISCOUNT_TYPE_AMOUNT;
		$invoice->discount_amount = $global_discount_amount;
		$invoice->subtotal = $global_subtotal;
		$invoice->tax_amount = $global_tax_amount;
		$invoice->balance_due = $global_total;
		$invoice->total = $global_total;
		$invoice->invoice_terms = $invoice_terms;
		$invoice->send_email = $send_email;
		$invoice->payment_method = $payment_method;
		$invoice->pattern_matched = $patten_matched;
		$invoice->scan_chars = $scan_chars;
		$invoice->settings_snapshot = json_encode($settings_snapshot);
		$invoice->save();

		/* now for invoice items */
		$invoice_items = [];
		foreach($rows as $row){

			$temp = [];

			$temp['product_id'] = Sanitize::input($row['product_id']);
			$temp['description'] = Sanitize::input($row['description'] ?? '');
			$temp['unit_price'] = Sanitize::input($row['unit_price']);
			$temp['quantity'] = Sanitize::input($row['quantity']);
			$temp['tax'] = Sanitize::input($row['tax']);
			$temp['tax_amount'] = Sanitize::input($row['tax_amount']);
			$temp['line_subtotal'] = Sanitize::input($row['line_subtotal']);
			$temp['line_total'] = Sanitize::input($row['line_total']);

			$invoice_items[] = $temp;
			
		}

		InvoiceItem::insert($invoice_items);

		$invoice_items = null;

		/* custom fields insertion */
		$this->upsertCustomFieldValues($request, $invoice->id, InvoicesCustomField::class, InvoiceCustomFieldValue::class, 'invoices_flat', 'invoice', true);

		$this->invoice_service->insertProductRows($request, $invoice->id, $company_id);

		/* override manual reset here */
		$this->invoice_service->resetManualInvoieNumberResetFlag($company_id);



	}

}
