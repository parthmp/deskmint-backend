<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceRepository{

	/**
	 * upsertInvoiceData function
	 *
	 * @param Request $request
	 * @param array $data
	 * @param integer $invoice_id
	 * @return array
	 */
	public function upsertInvoiceData(Request $request, array $data, int $invoice_id = 0) : array {

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
		// $send_email = $data['send_email'];
		$payment_method = $data['payment_method'];
		$patten_matched = $data['patten_matched'];
		$scan_chars = $data['scan_chars'];
		$rows = $data['rows'];
		logger($data);
		$settings_snapshot = $settings->getProductColumns(); /* json text for product_columns settings from SettingsSection table, if it does not exist, it falls back to the default values */

		if($invoice_id === 0){
			$invoice = new Invoice();
		}else{
			$invoice = $this->fetchInvoiceObjById($invoice_id);
		}
		
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
		// $invoice->send_email = $send_email;
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

	/**
	 * fetchInvoiceByNumber function
	 *
	 * @param string $invoice_number
	 * @param integer $company_id
	 * @param boolean $last
	 * @return Invoice|null
	 */
	public function fetchInvoiceByNumber(string $invoice_number, int $company_id, bool $last = false) : ?Invoice {

		$query = Invoice::where([['company_id', '=', $company_id], ['invoice_number', '=', $invoice_number]]);

		if($last){
			$query = $query->orderBy('id', 'desc');
		}

		return $query->first();

	}

	/**
	 * insertInvoiceItems function
	 *
	 * @param array $invoice_items
	 * @return void
	 */
	public function upsertInvoiceItems(array $invoice_items, int $invoice_id = 0) : void {
		
		if($invoice_id > 0){
			$posted_uuids = array_column($invoice_items, 'row_uuid');
			InvoiceItem::where('invoice_id', $invoice_id)->whereNotIn('row_uuid', $posted_uuids)->delete();
			//update all except row_uuid & invoice_id
			InvoiceItem::upsert($invoice_items, ['row_uuid', 'invoice_id'], array_keys(array_diff_key($invoice_items[0], array_flip(['row_uuid', 'invoice_id']))));
		}else{
			InvoiceItem::insert($invoice_items);
		}

		
	}

	/**
	 * ifEInvoiceOn function
	 *
	 * @param integer $invoice_id
	 * @return boolean
	 */
	public function ifEInvoiceIsOn(int $invoice_id) : bool {

		$query = DB::table('invoices')->select('clients.e_invoice_enabled')->join('clients', 'clients.id', '=', 'invoices.client_id')->where('invoices.id', '=', $invoice_id)->first();
		return (int) $query->e_invoice_enabled === 1;

	}

	/**
	 * fetchById function
	 *
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchById(int $invoice_id) : array {
		
		//return Invoice::where('id', '=', $invoice_id)->with('client_wt')->first();
	
		return (array) DB::table('invoices')->select('invoices.*', 'currencies.id as currency_id', 'currencies.code as currency_code', 'clients.first_name', 'clients.last_name')->join('clients', 'clients.id', 'invoices.client_id')->join('currencies', 'currencies.id', '=', 'clients.currency_id')->where('invoices.id', '=', $invoice_id)->first();
		
	}

	/**
	 * fetchInvoiceObjById function
	 *
	 * @param integer $invoice_id
	 * @return Invoice|null
	 */
	public function fetchInvoiceObjById(int $invoice_id) : ?Invoice {
		return Invoice::where('id', '=', $invoice_id)->first();
	}

	/**
	 * fetchCustomProductColumnValues function
	 *
	 * @param integer $invoice_id
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchCustomProductColumnValues(int $invoice_id, int $company_id) : array {

		return DB::table('additional_product_columns_field_values as apcv')->select('apcv.value', 'apcv.apc_field_id', 'apc.label', 'apc.type')->join('additional_product_columns_fields as apc', 'apc.id', '=', 'apcv.apc_field_id')->where([['apcv.invoice_id', '=', $invoice_id], ['apc.company_id', '=', $company_id]])->get()->toArray();

	}

	/**
	 * deleteRecordsByInvoiceIds function
	 *
	 * @param string $table
	 * @param array $ids
	 * @return void
	 */
	public function deleteRecordsByInvoiceIds(string $table, array $ids): void {
		if(Schema::hasTable($table)){
			DB::table($table)->whereIn('invoice_id', $ids)->delete();
		}
		Invoice::whereIn('id', $ids)->delete();
	}
	

	

}