<?php

namespace App\Repositories\Invoice;

use App\Enums\Credits\CreditStatus;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceLedger;
use App\Models\InvoiceSnapshot;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
		$send_email = $data['send_email'];
		$payment_gateway = $data['payment_gateway'];
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
			$invoice->status = (int) InvoiceStatus::DRAFT->value;
			if($send_email){
				$invoice->status = (int) InvoiceStatus::SENT->value;
			}
			$invoice->reminders_sent = 0;
			$invoice->last_reminder_sent_at = now();
		}else{
			$invoice = $this->fetchInvoiceObjById($invoice_id, $company_id);
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
		if($send_email){
			$invoice->sent_at = now();
		}else{
			$invoice->sent_at = null;
		}
		$invoice->payment_gateway = $payment_gateway;
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
	 * @param integer $company_id
	 * @return Invoice|null
	 */
	public function fetchInvoiceObjById(int $invoice_id, int $company_id) : ?Invoice {
		return Invoice::where([['id', '=', $invoice_id], ['company_id', '=', $company_id]])->first();
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
		return Invoice::whereIn('id', $invoice_ids)->whereIn('status', [InvoiceStatus::PARTIALLY_PAID->value, InvoiceStatus::PAID->value])->exists();
	}

	public function ifInvoiceLockedMultipleCancelled(array $invoice_ids) : bool {
		return Invoice::whereIn('id', $invoice_ids)->where('status', '=', (int) InvoiceStatus::CANCELLED->value)->exists();
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

	/**
	 * updateInvoiceStatus function
	 *
	 * @param integer $invoice_id
	 * @param integer $status
	 * @return boolean
	 */
	public function updateInvoiceStatus(int $invoice_id, int $status) : bool {
		return (bool) Invoice::where('id', '=', $invoice_id)->update([
			'status'	=>	$status
		]);
	}

	/**
	 * markInvoiceSent function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return boolean
	 */
	public function markInvoiceSent(int $company_id, int $invoice_id) : bool {

		$invoice = Invoice::where([['id', '=', $invoice_id], ['company_id', '=', $company_id]])->first();
		
		if((int) $invoice->status === InvoiceStatus::DRAFT->value){
			$invoice->status = InvoiceStatus::SENT->value;
			$invoice->sent_at = now();
			return $invoice->save();
		}

		return true;

	}

	/**
	 * fetchPartialInvoiceData function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return Invoice
	 */
	public function fetchPartialInvoiceData(int $company_id, int $invoice_id) : Invoice {
		$invoice = Invoice::select('client_id', 'currency_id', 'status', 'sent_at')->where([['id', '=', $invoice_id], ['company_id', '=', $company_id]])->first();
		return $invoice;
	}

	/**
	 * fetchFullInvoiceData function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return Invoice
	 */
	public function fetchFullInvoiceData(int $company_id, int $invoice_id) : Invoice {
		$invoice = Invoice::where([['id', '=', $invoice_id], ['company_id', '=', $company_id]])->first();
		return $invoice;
	}

	/**
	 * addCredit function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param string $amount
	 * @param string $uuid
	 * @return Credit
	 */
	public function addCredit(int $company_id, int $invoice_id, string $amount, string $uuid) : Credit {

		$invoice = $this->fetchPartialInvoiceData($company_id, $invoice_id);

		$credit = new Credit();
		$credit->company_id = $company_id;
		$credit->credit_number = $uuid;
		$credit->client_id = $invoice->client_id;
		$credit->currency_id = $invoice->currency_id;
		$credit->status = CreditStatus::NOT_APPLIED->value;
		$credit->amount = $amount;
		$credit->applied_amount = 0;
		$credit->amount_left_to_be_applied = $amount;
		$credit->save();
		return $credit;

	}

	/**
	 * addPayment function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param string $amount
	 * @param integer $payment_type
	 * @param string $uuid
	 * @param integer|null $transaction_id
	 * @return Payment
	 */
	public function addPayment(int $company_id, int $invoice_id, string $amount, int $payment_type, string $uuid, ?int $transaction_id = null) : Payment {

		$invoice = $this->fetchPartialInvoiceData($company_id, $invoice_id);

		$payment = new Payment();
		$payment->payment_number = $uuid;
		$payment->company_id = $company_id;
		$payment->client_id = $invoice->client_id;
		$payment->transaction_id = $transaction_id;
		$payment->payment_type_id = $payment_type;
		$payment->currency_id = $invoice->currency_id;
		$payment->status = PaymentStatus::NOT_APPLIED->value;
		$payment->amount = $amount;
		$payment->applied_amount = 0;
		$payment->amount_left_to_be_applied = $amount;
		$payment->save();

		return $payment;

	}

	/**
	 * overwriteCreditForAmount function
	 *
	 * @param Credit $credit
	 * @param string $apply_amount
	 * @return Credit
	 */
	public function overwriteCreditForAmount(Credit $credit, string $apply_amount) : Credit {

		$to_be_applied_amount = BigDecimal::of($apply_amount);
		$credit_total = BigDecimal::of($credit->amount);
		
		$status = CreditStatus::NOT_APPLIED->value;

		if($credit_total->isEqualTo($to_be_applied_amount)){
			$status = CreditStatus::APPLIED->value;
		}else if($to_be_applied_amount->isGreaterThan(BigDecimal::of(0)) && $credit_total->isLessThan($to_be_applied_amount)){
			$status = CreditStatus::PARTIALLY_APPLIED->value;
		}

		$amount_left_to_be_applied = $credit_total->minus($to_be_applied_amount);

		$credit->status = $status;
		$credit->applied_amount = $to_be_applied_amount->toScale(2, RoundingMode::HalfUp)->__toString();
		$credit->amount_left_to_be_applied = $amount_left_to_be_applied->toScale(2, RoundingMode::HalfUp)->__toString();
		$credit->save();

		return $credit;

	}

	/**
	 * overwritePaymentForAmount function
	 *
	 * @param Payment $payment
	 * @param string $apply_amount
	 * @return Payment
	 */
	public function overwritePaymentForAmount(Payment $payment, string $apply_amount) : Payment {

		$to_be_applied_amount = BigDecimal::of($apply_amount);
		$payment_total = BigDecimal::of($payment->amount);
		
		$status = PaymentStatus::NOT_APPLIED->value;

		if($payment_total->isEqualTo($to_be_applied_amount)){
			$status = PaymentStatus::APPLIED->value;
		}else if($to_be_applied_amount->isGreaterThan(BigDecimal::of(0)) && $payment_total->isLessThan($to_be_applied_amount)){
			$status = PaymentStatus::PARTIALLY_APPLIED->value;
		}

		$amount_left_to_be_applied = $payment_total->minus($to_be_applied_amount);

		$payment->status = $status;
		$payment->applied_amount = $to_be_applied_amount->toScale(2, RoundingMode::HalfUp)->__toString();
		$payment->amount_left_to_be_applied = $amount_left_to_be_applied->toScale(2, RoundingMode::HalfUp)->__toString();
		$payment->save();

		return $payment;

	}

	/**
	 * addLedgerEntry function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param integer $payment_or_credit_id
	 * @param string $applied_amount
	 * @param string $type
	 * @return boolean
	 */
	public function addLedgerEntry(int $company_id, int $invoice_id, int $payment_or_credit_id, string $applied_amount, string $type = 'credit') : bool{

		$ledger = new InvoiceLedger();
		$ledger->company_id = $company_id;
		$ledger->invoice_id = $invoice_id;
		if($type === 'credit'){
			//credit
			$ledger->payment_id = null;
			$ledger->credit_id = $payment_or_credit_id;
			$ledger->applied_amount_from_payments = 0;
			$ledger->applied_amount_from_credits = $applied_amount;
		}else{
			//payment
			$ledger->credit_id = null;
			$ledger->payment_id = $payment_or_credit_id;
			$ledger->applied_amount_from_payments = $applied_amount;
			$ledger->applied_amount_from_credits = 0;
		}

		$ledger->total_applied = $applied_amount;

		return $ledger->save();
		

	}

	/**
	 * ifPaymentTypeExists function
	 *
	 * @param integer $payment_type_id
	 * @return boolean
	 */
	public function ifPaymentTypeExists(int $payment_type_id) : bool {
		$counter = PaymentType::where('id', '=', $payment_type_id)->count();
		return $counter > 0;
	}

	/**
	 * fetchLedgerEntriesOfInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchLedgerEntriesOfInvoice(int $company_id, int $invoice_id) : array {
		return InvoiceLedger::select('total_applied', 'invoice_id')->where([['company_id', '=', $company_id], ['invoice_id', '=', $invoice_id]])->get()->toArray(); 
	}

	/**
	 * Undocumented function
	 *
	 * @param Invoice $invoice
	 * @param integer $status
	 * @param string $balance_due
	 * @return boolean
	 */
	public function updateInvoiceStatusAndAmount(Invoice $invoice, int $status, string $balance_due) : bool {

		$invoice->status = $status;
		$invoice->balance_due = $balance_due;
		return $invoice->save();

	}

	/**
	 * fetchByIdWithComapanyId function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return Invoice|null
	 */
	public function fetchByIdWithComapanyId(int $company_id, int $invoice_id) : ?Invoice {

		$selects = [
			'invoices.id as id',
			'invoices.invoice_number as invoice_number',
			'invoices.total as total',
			'invoices.balance_due as balance_due',
			'currencies.id as currency_id',
			'currencies.code as currency_code',
			'clients.first_name as first_name',
			'clients.last_name as last_name'
		];
		
		return Invoice::select($selects)->join('clients', 'clients.id', '=', 'invoices.client_id')->join('currencies', 'currencies.id', '=', 'invoices.currency_id')->where([['invoices.id', '=', $invoice_id], ['invoices.company_id', '=', $company_id]])->first();
		
	}

	public function fetchCreditsForApplyUnapply(int $company_id, int $currency_id, int $client_id, string $searched, array $locally_applied_ids, array $fully_applied_ids = []){

		$not_fulled_applied_credits = Credit::select('id as id', 'credit_number as credit', 'amount as amount', 'applied_amount as applied', 'amount_left_to_be_applied as left')->where([['currency_id', '=', $currency_id], ['company_id', '=', $company_id], ['client_id', '=', $client_id]])
			->whereNotIn('id', $locally_applied_ids)
			->where(function($q) {
				$q->where('status', '=', CreditStatus::NOT_APPLIED->value)
				->orWhere('status', '=', CreditStatus::PARTIALLY_APPLIED->value);
			})->when($searched, function ($query, $searched) {
				$query->where(function ($q) use ($searched) {

					$q->where('credit_number', 'like', "%{$searched}%")
					->orWhere('id', 'like', "%{$searched}%")
					->orWhere('amount', 'like', "%{$searched}%")
					->orWhere('applied_amount', 'like', "%{$searched}%")
					->orWhere('amount_left_to_be_applied', 'like', "%{$searched}%");
				});
			})
			->orderBy('id', 'desc')->limit(50)->get()->toArray();

		// $paid_invoices = [];

		// if(!empty($fully_applied_ids)){
			
		// 	$applied_credits = Credit::select('credits.id as id', 'credits.credit_number as credit', 'credits.amount as amount', 'credits.applied_amount as applied_amount', 'il.applied_amount_from_credits as applied_amount')
		// 	->join('invoice_ledger as il', 'il.invoice_id', '=', 'invoices.id')
		// 	->where([['invoices.currency_id', '=', $currency_id], ['invoices.company_id', '=', $company_id], ['invoices.client_id', '=', $client_id], ['il.credit_id', '=', $credit_id]])
		// 	->whereIn('invoices.id', $fully_applied_ids)
		// 	->where('invoices.status', '=', InvoiceStatus::PAID->value)->when($searched, function ($query, $searched) {
		// 		$query->where(function ($q) use ($searched) {

		// 			$q->where('invoices.invoice_number', 'like', "%{$searched}%")
		// 			->orWhere('invoices.id', 'like', "%{$searched}%")
		// 			->orWhere('invoices.balance_due', 'like', "%{$searched}%")
		// 			->orWhere('invoices.total', 'like', "%{$searched}%");
		// 		});
		// 	})
		// 	->orderBy('invoices.id', 'desc')->limit(50)->get()->toArray();

		// }

	}

}