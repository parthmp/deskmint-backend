<?php

namespace App\Repositories\Invoice;

use App\Helpers\Sanitize;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicesCustomField;
use App\Services\HandleInvoiceNumbers;
use App\Services\Invoice\InvoiceService;
use App\Services\InvoiceSettingsService;
use App\Traits\CustomFieldsPrinting;
use App\Traits\PaymentGatewayDetails;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InvoiceRepository{

	use PaymentGatewayDetails, CustomFieldsPrinting;

	private InvoiceService $invoice_service;

	public function __construct(InvoiceService $invoice_service){
		$this->invoice_service = $invoice_service;
	}

	/**
	 * getInitialData function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @param integer $timezone_offset_minutes
	 * @return array
	 */
	public function getInitialData(Request $request, int $company_id, int $timezone_offset_minutes) : array {
		
		$invoice_settings = new InvoiceSettingsService((int) $company_id);

		$custom_fields = $this->fetchInvoiceCustomFields($request);

		/* get payment integration data */
		$gateways = $this->getGateWayNames((int) $company_id);


		return [
			'invoice_number'	=>	(new HandleInvoiceNumbers((int) $company_id, $invoice_settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->getNextInvoiceNumber(),
			'product_columns' 	=> 	$invoice_settings->getProductColumns(),
			'total_fields' 		=> 	$invoice_settings->getTotalFields(),
			'custom_fields'		=>	$custom_fields['data_fields'],
			'gateways'			=>	$gateways
		];
		
	}

	/**
	 * fetchInvoiceCustomFields function
	 *
	 * @param Request $request
	 * @return array
	 */
	private function fetchInvoiceCustomFields(Request $request) : array{

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = InvoicesCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();

		return 	[
					'data_fields' 	=> $this->adjustRowsPrinting($fields),
				];
	}

	public function insertInvoiceData(Request $request) : array {

		$data = $this->invoice_service->getInvoiceInsertData($request);

		$settings = $data['settings'];
		$client_id = $data['client_id'];
		$company_id = $data['company_id'];
		$invoice_number = $data['invoice_number'];
		$invoice_date = $data['invoice_date'];
		$due_date = $data['due_date'];
		$po_number = $data['po_number'];
		$discount_number = $data['discount_number'];
		$discount_type = $data['discount_type'];
		$global_discount_amount = $data['global_discount_amount'];
		$global_subtotal = $data['global_subtotal'];
		$global_tax_amount = $data['global_tax_amount'];
		$global_total = $data['global_total'];
		$invoice_terms = $data['invoice_terms'];
		$send_email = $data['send_email'];
		$payment_method = $data['payment_method'];
		$patten_matched = $data['patten_matched'];
		$scan_chars = $data['scan_chars'];
		$rows = $data['rows'];
		
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

		return [
			'invoice'	=>	$invoice,
			'rows'		=>	$rows
		];
		
	}

	public function insertInvoice(Request $request) : int {

		$data = $this->insertInvoiceData($request);
		$invoice = $data['invoice'];
		$rows = $data['rows'];

		$invoice_items = [];
		foreach($rows as $row){

			$temp = [];

			$temp['invoice_id'] = $invoice->id;
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

		return $invoice->id;

	}

}