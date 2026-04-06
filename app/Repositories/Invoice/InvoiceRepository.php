<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InvoiceRepository{

	public function insertInvoiceData(Request $request, array $data) : array {

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

	
}