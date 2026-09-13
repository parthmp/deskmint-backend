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
	 * @param string $credit_number
	 * @param integer|null $id
	 * @return Credit
	 */
	public function createOrUpdate(int $company_id, int $client_id, int $currency_id, string $amount, string $applied_amount, string $amount_left_to_apply, int $status, string $credit_number, ?int $id = null) : Credit {

		if(!$id){
			$credit = new Credit();
			$credit->company_id = $company_id;
			
		}else{
			$credit = $this->fetchById((int) $company_id, (int) $id);
		}
		$credit->credit_number = $credit_number;
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
			$q->where('status', '=', CreditStatus::APPLIED->value)->orWhere('status', '=', CreditStatus::PARTIALLY_APPLIED->value);
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
	 * ifCreditNumberExists function
	 *
	 * @param integer $company_id
	 * @param string $credit_number
	 * @param integer|null $ignore_id
	 * @return boolean
	 */
	public function ifCreditNumberExists(int $company_id, string $credit_number, ?int $ignore_id = null) : bool {

		$conditions = [['credit_number', '=', $credit_number], ['company_id', '=', $company_id]];

		if($ignore_id){
			array_push($conditions, ['id', '<>', $ignore_id]);
		}

		$found = Credit::where($conditions)->count();

		return (int) $found > 0;
	}

}