<?php

namespace App\Repositories\Credit;

use App\Enums\Credits\CreditStatus;
use App\Helpers\General;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceLedger;
use App\Modules\Payment\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CreditRepository {

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return Credit|null
	 */
	public function fetchById(int $company_id, int $id) : ?Credit {
		return Credit::where([['id', '=', $id], ['company_id', '=', $company_id]])->first();
	}

	/**
	 * fetchClientCurrencyId function
	 *
	 * @param integer $client_id
	 * @return integer
	 */
	public function fetchClientCurrencyId(int $company_id, int $client_id) : int {
		$client = Client::select('currency_id')->where([['id', '=', $client_id], ['company_id', '=', $company_id]])->first();
		return $client->currency_id;
	}

	/**
	 * createOrUpdate function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param string $amount
	 * @param string $applied_amount
	 * @param string $amount_left_to_apply
	 * @param integer $status
	 * @param integer|null $id
	 * @return Credit
	 */
	public function createOrUpdate(int $company_id, int $client_id, int $currency_id, string $amount, string $applied_amount, string $amount_left_to_apply, int $status, ?int $id = null) : Credit {

		if(!$id){
			$credit = new Credit();
			$credit->company_id = $company_id;
		}else{
			$credit = $this->fetchById((int) $company_id, (int) $id);
		}
		
		$credit->client_id = $client_id;
		$credit->currency_id = $currency_id;
		$credit->status = $status;
		$credit->amount = $amount;
		$credit->applied_amount = $applied_amount;
		$credit->amount_left_to_be_applied = $amount_left_to_apply;
		$credit->save();

		return $credit;

	}

	/**
	 * ifAnyCreditsAreApplied function
	 *
	 * @param array $ids
	 * @return boolean
	 */
	public function ifAnyCreditsAreApplied(array $ids) : bool {
		$counted = Credit::where(function($q){
			$q->where('status', '=', CreditStatus::APPLIED)->orWhere('status', '=', CreditStatus::PARTIALLY_APPLIED);
		})->whereIn('id', $ids)->count();
		return (int) $counted > 0;
	}

	/**
	 * deleteMultipleCredits function
	 *
	 * @param array $ids
	 * @return bool
	 */
	public function deleteMultipleCredits(array $ids) : bool {
		return Credit::whereIn('id', $ids)->delete();
	}

	/**
	 * fetchCreditForEdit function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return array
	 */
	public function fetchCreditForEdit(int $company_id, int $id) : array {
		
		$credit = Credit::select('credits.*', 'clients.full_name as full_name', 'credit_currencies.code as credit_currency', 'client_currencies.code as client_currency')->join('clients', 'clients.id', '=', 'credits.client_id')->join('currencies as credit_currencies', 'credits.currency_id', '=', 'credit_currencies.id')->join('currencies as client_currencies', 'clients.currency_id', '=', 'client_currencies.id')->where([['credits.company_id', '=', $company_id], ['credits.id', '=', $id]])->first();
		
		$credit = $credit->toArray();
		
		$credit['status_text'] = CreditStatus::NOT_APPLIED->label();
		if((int) $credit['status'] === CreditStatus::PARTIALLY_APPLIED->value){
			$credit['status_text'] = CreditStatus::PARTIALLY_APPLIED->label();
		}else if((int) $credit['status'] === CreditStatus::APPLIED->value){
			$credit['status_text'] = CreditStatus::APPLIED->label();
		}

		return $credit;
		
	}

	/**
	 * fetchAppliedCreditInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @return array
	 */
	public function fetchAppliedCreditInvoices(int $company_id, int $credit_id) : array {

		$invoices = Credit::select('il.applied_amount_from_credits as applied_amount', 'invoices.invoice_number', 'il.created_at as applied_on')
							->join('invoice_ledger as il', 'il.credit_id', '=', 'credits.id')
							->join('invoices', 'il.invoice_id', '=', 'invoices.id')
							->where([['credits.company_id', '=', $company_id], ['credits.id', '=', $credit_id]])->get();
		
		$invoices = $invoices->toArray();

		foreach($invoices as $key => $entry){
			foreach($entry as $sub_key => $sub_entry){
				if(General::isMySQLDateTime($sub_entry)){
					$invoices[$key][$sub_key] = Carbon::parse($sub_entry)->toISOString();
				}
			}
		}

		return $invoices;

	}

	/**
	 * fetchCreditWithCurrencyInfo function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @return array
	 */
	public function fetchCreditWithCurrencyInfo(int $company_id, int $credit_id) : array {
		return Credit::select(
							'credits.id as id',
							'credits.client_id as client_id',
							'credits.amount as amount',
							'credits.amount_left_to_be_applied as left',
							'currencies.code as currency_code',
							'currencies.id as currency_id',
							'clients.full_name as full_name'
						)
						->join('clients', 'clients.id', '=', 'credits.client_id')
						->join('currencies', 'currencies.id', '=', 'credits.currency_id')
						->where([['credits.company_id', '=', $company_id], ['credits.id', '=', $credit_id]])
						->first()
						->toArray();
	}

	/**
	 * searchInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param array $applied_ids
	 * @param string $searched
	 * @return array
	 */
	public function searchInvoices(int $company_id, int $currency_id, int $client_id, array $applied_ids, string $searched) : array {
		
		$invoices = Invoice::select('id as id', 'invoice_number as invoice', 'total as total', 'balance_due as due')->where([['currency_id', '=', $currency_id], ['company_id', '=', $company_id], ['client_id', '=', $client_id]])
			->whereNotIn('id', $applied_ids)
			->where(function($q) {
				$q->where('status', '=', InvoiceStatus::SENT->value)
				->orWhere('status', '=', InvoiceStatus::PARTIALLY_PAID->value);
			})->when($searched, function ($query, $searched) {
				$query->where(function ($q) use ($searched) {

					$q->where('invoice_number', 'like', "%{$searched}%")
					->orWhere('id', 'like', "%{$searched}%")
					->orWhere('balance_due', 'like', "%{$searched}%")
					->orWhere('total', 'like', "%{$searched}%");
				});
			})
			->orderBy('id', 'desc')->limit(50)->get()->toArray();

			return $invoices;

	}

	/**
	 * fetchAppliedInvoicesForCredit function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @return array
	 */
	public function fetchAppliedInvoicesForCredit(int $company_id, int $credit_id) : array {
		return InvoiceLedger::where([['company_id', '=', $company_id], ['credit_id', '=', $credit_id]])->pluck('invoice_id')->toArray();
	}

	/**
	 * fetchAppliedCreditsLedger function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @param array $invoice_ids
	 * @return Collection
	 */
	public function fetchAppliedCreditsLedger(int $company_id, int $credit_id, array $invoice_ids) : Collection {
		return InvoiceLedger::select('id as ledger_id', 'applied_amount_from_credits as applied_credit', 'invoice_id as invoice_id')->where([['company_id', '=', $company_id], ['credit_id', '=', $credit_id]])->whereIn('invoice_id', $invoice_ids)->get();
	}

	/**
	 * Undocumented function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @return Collection
	 */
	public function fetchMultipleInvoicesByIds(int $company_id, array $ids) : Collection {

		return Invoice::select('id', 'balance_due')->where('company_id', '=', $company_id)->whereIn('id', $ids)->get();
		
	}

	/**
	 * fetchCountForClientInvoicesWithIds function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param array $ids
	 * @return integer
	 */
	public function fetchCountForClientInvoicesWithIds(int $company_id, int $client_id, int $currency_id, array $ids) : int {
		$counted = Invoice::where([['company_id', '=', $company_id], ['client_id', '=', $client_id], ['currency_id', '=', $currency_id]])->whereIn('id', $ids)->count();
		return $counted;
	}

	/**
	 * updateCreditForApplying function
	 *
	 * @param Credit $credit
	 * @param integer $status
	 * @param string $applied_amount
	 * @param string $left_amount
	 * @return boolean
	 */
	public function updateCreditForApplying(Credit $credit, int $status, string $applied_amount, string $left_amount) : bool {
		$credit->status = $status;
		$credit->applied_amount = $applied_amount;
		$credit->amount_left_to_be_applied = $left_amount;
		return $credit->save();
	}

	/**
	 * forceRemoveLedgreEntriesForCredit function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @return void
	 */
	public function forceRemoveLedgreEntriesForCredit(int $company_id, int $credit_id) : void {
		InvoiceLedger::where([['company_id', '=', $company_id], ['credit_id', '=', $credit_id]])->forceDelete();
	}

	/**
	 * insertLedgerEntries function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @param array $data
	 * @return void
	 */
	public function insertLedgerEntries(int $company_id, int $credit_id, array $data) : void {

		$insert = [];

		foreach($data as $row){
			$insert[] = [
				'company_id'						=>	$company_id,
				'invoice_id'						=>	(int) $row['invoice_id'],
				'payment_id'						=>	null,
				'credit_id'							=>	$credit_id,
				'applied_amount_from_payments'		=>	0,
				'applied_amount_from_credits'		=>	$row['applied_amount'],
				'total_applied'						=>	$row['applied_amount']
			];
		}

		InvoiceLedger::insert($insert);

	}

	/**
	 * fetchInvoicesForCreditApplying function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @return array
	 */
	public function fetchInvoicesForCreditApplying(int $company_id, array $ids) : array {
		return Invoice::select('id', 'total', 'balance_due', 'status')->where('company_id', '=', $company_id)->whereIn('id', $ids)->get()->toArray();
	}

	/**
	 * fetchLedgerForCreditApplying function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @return array
	 */
	public function fetchLedgerForCreditApplying(int $company_id, array $ids) : array {
		return InvoiceLedger::select('total_applied', 'invoice_id')->where('company_id', '=', $company_id)->whereIn('invoice_id', $ids)->get()->toArray();
	}

	
	/**
	 * updateInvoicesForCreditApplying function
	 *
	 * @param array $upsert
	 * @return void
	 */
	public function updateInvoicesForCreditApplying(array $upsert): void {

		if(empty($upsert)){
			return;
		}

		$status_case = 'CASE id ';
		$balance_case = 'CASE id ';
		$ids_placeholder = [];
		$bindings = [];

		foreach($upsert as $row){
			$status_case .= 'WHEN ? THEN ? ';
			$balance_case .= 'WHEN ? THEN ? ';
			$ids_placeholder[] = '?';
		}

		$status_case .= 'END';
		$balance_case .= 'END';

		foreach($upsert as $row){
			$bindings[] = $row['id'];
			$bindings[] = $row['status'];
		}
		foreach($upsert as $row){
			$bindings[] = $row['id'];
			$bindings[] = $row['balance_due'];
		}
		foreach($upsert as $row){
			$bindings[] = $row['id'];
		}

		$sql = "UPDATE invoices SET
					status = ($status_case),
					balance_due = ($balance_case),
					updated_at = NOW()
				WHERE id IN (".implode(',', $ids_placeholder).")";

		DB::update($sql, $bindings);
	}


	/**
	 * fetchAlreadyAppliedInvoicesForCredit function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @return array
	 */
	public function fetchAlreadyAppliedInvoicesForCredit(int $company_id, int $credit_id) : array {
		return Invoice::select('invoices.id as id', 'invoices.invoice_number as invoice', 'invoices.total as total', 'invoices.balance_due as due', 'il.applied_amount_from_credits as amount')->join('invoice_ledger as il', 'il.invoice_id', '=', 'invoices.id')->where([['il.company_id', '=', $company_id], ['il.credit_id', '=', $credit_id]])->get()->toArray();
	}

}