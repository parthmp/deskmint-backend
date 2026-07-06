<?php

namespace App\Repositories\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSnapshot;
use App\Models\Transaction;
use App\Modules\Payment\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;


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
		$global_discount_amount = $data['global_discount_amount_post_tax'];
		$global_discount_amount_pre_tax = $data['global_discount_amount_pre_tax'];
		$global_subtotal = $data['global_subtotal'];
		$global_tax_amount = $data['global_tax_amount'];
		$global_total = $data['global_total'];
		$invoice_terms = $data['invoice_terms'];
		// $send_email = $data['send_email'];
		$payment_method = $data['payment_method'];
		$patten_matched = $data['patten_matched'];
		$scan_chars = $data['scan_chars'];
		$timezone_offset_minutes = $data['timezone_offset_minutes'];

		$first_name = $data['first_name'];
		$last_name = $data['last_name'];
		$full_name = $data['full_name'];
		$client_company = $data['client_company'];
		$currency_id = $data['currency_id'];

		$rows = $data['rows'];
		
		$settings_snapshot = $settings->getProductColumns(); /* json text for product_columns settings from SettingsSection table, if it does not exist, it falls back to the default values */

		if($invoice_id === 0){
			$invoice = new Invoice();
			$invoice->uuid = Str::uuid();
			$invoice->status = (int) InvoiceStatus::PENDING->value;
		}else{
			$invoice = $this->fetchInvoiceObjById($invoice_id);
		}
		
		$invoice->client_id = $client_id;

		$invoice->first_name = $first_name;
		$invoice->last_name = $last_name;
		$invoice->full_name = $full_name;
		$invoice->client_company = $client_company;
		$invoice->currency_id = $currency_id;

		$invoice->company_id = $company_id;
		$invoice->invoice_number = $invoice_number;
		$invoice->invoice_date = Carbon::parse($invoice_date);
		$invoice->due_date = Carbon::parse($due_date);
		$invoice->po_number = $po_number;
		$invoice->discount = $discount_number;
		$invoice->discount_type = ($discount_type === 'percentage') ? DISCOUNT_TYPE_PERCENTAGE : DISCOUNT_TYPE_AMOUNT;
		$invoice->discount_amount_post_tax = $global_discount_amount;
		$invoice->discount_amount_pre_tax = $global_discount_amount_pre_tax;
		$invoice->subtotal = $global_subtotal;
		$invoice->tax_amount = $global_tax_amount;
		$invoice->balance_due = $global_total;
		$invoice->total = $global_total;
		$invoice->invoice_terms = $invoice_terms;
		// $invoice->send_email = $send_email;
		$invoice->payment_method = $payment_method;
		$invoice->pattern_matched = $patten_matched;
		$invoice->scan_chars = $scan_chars;
		$invoice->timezone_offset_minutes = $timezone_offset_minutes;
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
			// $posted_uuids = array_column($invoice_items, 'row_uuid');
			InvoiceItem::where('invoice_id', '=', $invoice_id)->forceDelete();
			//update all except row_uuid & invoice_id
			//InvoiceItem::upsert($invoice_items, ['row_uuid', 'invoice_id'], array_keys(array_diff_key($invoice_items[0], array_flip(['row_uuid', 'invoice_id']))));
			// InvoiceItem::insert($invoice_items);
		}

		$now = now();
		foreach($invoice_items as &$item){
			$item['created_at'] = $now;
			$item['updated_at'] = $now;
		}
		
		InvoiceItem::insert($invoice_items);
		
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
	 * @return Invoice|null
	 */
	public function fetchById(int $invoice_id) : ?Invoice {
		
		//return Invoice::where('id', '=', $invoice_id)->with('client_wt')->first();
	
		//return (array) DB::table('invoices')->select('invoices.*', 'currencies.id as currency_id', 'currencies.code as currency_code', 'clients.first_name', 'clients.last_name')->join('clients', 'clients.id', 'invoices.client_id')->join('currencies', 'currencies.id', '=', 'clients.currency_id')->where('invoices.id', '=', $invoice_id)->first();

		return Invoice::select('invoices.*', 'currencies.id as currency_id', 'currencies.code as currency_code', 'clients.first_name', 'clients.last_name')->join('clients', 'clients.id', '=', 'invoices.client_id')->join('currencies', 'currencies.id', '=', 'clients.currency_id')->where('invoices.id', $invoice_id)->first();
		
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

	/**
	 * fetchLogoImage function
	 *
	 * @param integer $company_id
	 * @return string
	 */
	public function fetchLogoImage(int $company_id) : string {
		$company = Company::select('logo')->where('id', '=', $company_id)->first();
		return $company->logo;
	}
	
	/**
	 * fetchInvoiceSnapshot function
	 *
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchInvoiceSnapshot(int $invoice_id) : array {
		$snapshot = InvoiceSnapshot::where('invoice_id', '=', $invoice_id)->first();
		return $snapshot->snapshot;
	}
	
	/**
	 * ifInvoiceLocked function
	 *
	 * @param integer $invoice_id
	 * @return boolean
	 */
	public function ifInvoiceLocked(int $invoice_id) : bool {

		$transactions = Transaction::where([['invoice_id', '=', $invoice_id], ['is_payment_captured', '=', 1]])->count();
		return $transactions > 0;

	}

	/**
	 * ifInvoiceLockedMultiple function
	 *
	 * @param array $invoice_ids
	 * @return boolean
	 */
	public function ifInvoiceLockedMultiple(array $invoice_ids) : bool {
		return Transaction::whereIn('invoice_id', $invoice_ids)->where('is_payment_captured', 1)->exists();
	}

	/**
	 * updateInvoiceFiles function
	 *
	 * @param integer $invoice_id
	 * @param string $pdf_file
	 * @param string $xml_file
	 * @return boolean
	 */
	public function updateInvoiceFiles(int $invoice_id, string $pdf_file, string $xml_file) : bool {

		return (bool) Invoice::where('id', '=', $invoice_id)->update([
			'pdf_file'	=>	$pdf_file,
			'xml_file'	=>	$xml_file
		]);

	}

	/**
	 * fetchInvoiceWithClientAndCurrency function
	 *
	 * @param integer $invoice_id
	 * @return Invoice|null
	 */
	public function fetchInvoiceWithClientAndCurrency(int $invoice_id) : ?Invoice {
		return Invoice::where('id', '=', $invoice_id)->with('client_wt', 'currency')->withTrashed()->first();
	}


}