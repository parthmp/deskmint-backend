<?php

namespace App\Modules\ApplyUnapply\CreditsAndPayments\DB;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceLedger;
use App\Models\Payment;
use App\Modules\Payment\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB as FacadesDB;

class DB {
	
	/**
	 * fetchAlreadyAppliedInvoicesForEntry function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @param string $type
	 * @return array
	 */
	public function fetchAlreadyAppliedInvoicesForEntry(int $company_id, int $id, string $type) : array {
		return Invoice::select('invoices.id as id', 'invoices.invoice_number as invoice', 'invoices.total as total', 'invoices.balance_due as due', 'il.applied_amount_from_'.$type.'s as amount')->join('invoice_ledger as il', 'il.invoice_id', '=', 'invoices.id')->where([['il.company_id', '=', $company_id], ['il.'.$type.'_id', '=', $id]])->get()->toArray();
	}

	/**
	 * fetchEntryWithCurrencyInfo function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @param string $type
	 * @return array
	 */
	public function fetchEntryWithCurrencyInfo(int $company_id, int $id, string $type) : array {

		$model = Credit::class;
		if($type === 'payment'){
			$model = Payment::class;
		}

		return $model::select(
							$type.'s.id as id',
							$type.'s.client_id as client_id',
							$type.'s.amount as amount',
							$type.'s.amount_left_to_be_applied as left',
							'currencies.code as currency_code',
							'currencies.id as currency_id',
							'clients.full_name as full_name'
						)
						->join('clients', 'clients.id', '=', $type.'s.client_id')
						->join('currencies', 'currencies.id', '=', $type.'s.currency_id')
						->where([[$type.'s.company_id', '=', $company_id], [$type.'s.id', '=', $id]])
						->first()
						->toArray();
	}

	
	/**
	 * fetchLedgerEntries function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @param array $not_in_ids
	 * @param string $type
	 * @return array
	 */
	public function fetchLedgerEntries(int $company_id, int $id, array $not_in_ids, string $type) : array {
		$entries = InvoiceLedger::select('invoice_id as invoice_id', 'applied_amount_from_'.$type.'s as applied_amount')->where([['company_id', '=', $company_id], [$type.'_id', '=', $id]])->whereNotIn('invoice_id', $not_in_ids)->get()->toArray();
		return $entries;
	}

	
	/**
	 * searchInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param integer $id
	 * @param array $applied_ids
	 * @param array $paid_ids
	 * @param string $searched
	 * @param string $type
	 * @return array
	 */
	public function searchInvoices(int $company_id, int $currency_id, int $client_id,  int $id, array $applied_ids, array $paid_ids, string $searched, string $type) : array {
		
		$unpaid_invoices_raw = Invoice::select('id as id', 'invoice_number as invoice', 'total as total', 'balance_due as due')->where([['currency_id', '=', $currency_id], ['company_id', '=', $company_id], ['client_id', '=', $client_id]])
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

		$paid_invoices = [];

		if(!empty($paid_ids)){
			
			$paid_invoices = Invoice::select('invoices.id as id', 'invoices.invoice_number as invoice', 'invoices.total as total', 'invoices.balance_due as due', 'il.applied_amount_from_'.$type.'s as applied_amount')
			->join('invoice_ledger as il', 'il.invoice_id', '=', 'invoices.id')
			->where([['invoices.currency_id', '=', $currency_id], ['invoices.company_id', '=', $company_id], ['invoices.client_id', '=', $client_id], ['il.'.$type.'_id', '=', $id]])
			->whereIn('invoices.id', $paid_ids)
			->where('invoices.status', '=', InvoiceStatus::PAID->value)->when($searched, function ($query, $searched) {
				$query->where(function ($q) use ($searched) {

					$q->where('invoices.invoice_number', 'like', "%{$searched}%")
					->orWhere('invoices.id', 'like', "%{$searched}%")
					->orWhere('invoices.balance_due', 'like', "%{$searched}%")
					->orWhere('invoices.total', 'like', "%{$searched}%");
				});
			})
			->orderBy('invoices.id', 'desc')->limit(50)->get()->toArray();

		}

		//weave for unpaid invoices to have partially paid invoices applied_amount.
		$entries = $this->fetchLedgerEntries($company_id, $id, $applied_ids, $type);
		$unpaid_invoices = [];
		foreach($unpaid_invoices_raw as $unpaid_invoice_raw){
			$temp = $unpaid_invoice_raw;
			$temp['applied_amount'] = '';
			foreach($entries as $entry){
				if((int) $entry['invoice_id'] === (int) $unpaid_invoice_raw['id']){
					$temp['applied_amount'] = $entry['applied_amount'];
					break;
				}
			}
			$unpaid_invoices[] = $temp;
		}
		
		
		return [
			'unpaid_invoices'	=>	$unpaid_invoices,
			'paid_invoices'		=>	$paid_invoices
		];

	}

	/**
	 * fetchById function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @param string $type
	 * @return null|Credit|Payment
	 */
	public function fetchById(int $company_id, int $id, string $type) : null|Credit|Payment {
		$model = Credit::class;
		if($type === 'payment'){
			$model = Payment::class;
		}
		return $model::where([['id', '=', $id], ['company_id', '=', $company_id]])->first();
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
	 * fetchMultipleInvoicesByIds function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @return Collection
	 */
	public function fetchMultipleInvoicesByIds(int $company_id, array $ids) : Collection {
		return Invoice::select('id', 'balance_due')->where('company_id', '=', $company_id)->whereIn('id', $ids)->get();
	}

	
	/**
	 * fetchAppliedEntriesLedger function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @param array $invoice_ids
	 * @param string $type
	 * @return Collection
	 */
	public function fetchAppliedEntriesLedger(int $company_id, int $id, array $invoice_ids, string $type) : Collection {
		return InvoiceLedger::select('id as ledger_id', 'applied_amount_from_'.$type.'s as applied_'.$type, 'invoice_id as invoice_id')->where([['company_id', '=', $company_id], [$type.'_id', '=', $id]])->whereIn('invoice_id', $invoice_ids)->get();
	}

	/**
	 * removeLedgerEntries function
	 *
	 * @param integer $company_id
	 * @param integer $entry_id
	 * @param array $removed_invoice_ids
	 * @param string $type
	 * @return void
	 */
	public function removeLedgerEntries(int $company_id, int $entry_id, array $removed_invoice_ids, string $type) : void {
		InvoiceLedger::where([['company_id', '=', $company_id], [$type.'_id', '=', $entry_id]])->whereIn('invoice_id', $removed_invoice_ids)->forceDelete();
	}

	/**
	 * fetchAppliedEntries function
	 *
	 * @param integer $company_id
	 * @param integer $entry_id
	 * @param array $applied_ids
	 * @param string $type
	 * @return array
	 */
	public function fetchAppliedEntries(int $company_id, int $entry_id, array $applied_ids, string $type) : array {
		return InvoiceLedger::where([['company_id', '=', $company_id], [$type.'_id', '=', $entry_id]])->whereIn('invoice_id', $applied_ids)->orderBy('invoice_id', 'asc')->get()->toArray();
	}

	/**
	 * fetchLedgerEntriesForEntry function
	 *
	 * @param integer $company_id
	 * @param integer $entry_id
	 * @param string $type
	 * @return array
	 */
	public function fetchLedgerEntriesForEntry(int $company_id, int $entry_id, string $type) : array {
		return InvoiceLedger::where([['company_id', '=', $company_id], [$type.'_id', '=', $entry_id]])->get()->toArray();
	}

	/**
	 * fetchEntryDetails function
	 *
	 * @param integer $company_id
	 * @param integer $entry_id
	 * @param string $type
	 * @return null|Credit|Payment
	 */
	public function fetchEntryDetails(int $company_id, int $entry_id, string $type) : null|Credit|Payment {
		
		$model = Credit::class;
		if($type === 'payment'){
			$model = Payment::class;
		}

		return $model::where([['company_id', '=', $company_id], ['id', '=', $entry_id]])->first();

	}

	/**
	 * updateEntry function
	 *
	 * @param integer $company_id
	 * @param integer $entry_id
	 * @param integer $status
	 * @param string $applied_amount
	 * @param string $amount_left
	 * @param string $type
	 * @return void
	 */
	public function updateEntry(int $company_id, int $entry_id, int $status, string $applied_amount, string $amount_left, string $type) : void {

		$model = Credit::class;
		if($type === 'payment'){
			$model = Payment::class;
		}

		$model::where([['company_id', '=', $company_id], ['id', '=', $entry_id]])->update([
			'status'					=>	$status,
			'applied_amount'			=>	$applied_amount,
			'amount_left_to_be_applied'	=>	$amount_left,
			'updated_at'				=>	now()
		]);

	}

	/**
	 * fetchInvoicesLedgerEntries function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @return array
	 */
	public function fetchInvoicesLedgerEntries(int $company_id, array $invoice_ids) : array {
		return InvoiceLedger::where('company_id', '=', $company_id)->whereIn('invoice_id', $invoice_ids)->orderBy('invoice_id', 'asc')->get()->toArray();
	}

	/**
	 * fetchInvoiceDataByIds function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @param array $selects
	 * @return array
	 */
	public function fetchInvoiceDataByIds(int $company_id, array $invoice_ids, array $selects = ['*']) : array {
		return Invoice::select(...$selects)->where([['company_id', '=', $company_id]])->whereIn('id', $invoice_ids)->orderBy('id', 'asc')->get()->toArray();
	}

	/**
	 * updateInvoices function
	 *
	 * @param array $chunks
	 * @return void
	 */
	public function updateInvoices(array $chunks) : void {

		foreach($chunks as $chunk){

			$ids = array_column($chunk, 'id');

			$status_case  = 'CASE id ';
			$balance_due_case = 'CASE id ';
			
			$status_bindings  = [];
			$balance_due_bindings = [];
			
			foreach($chunk as $row){

				$status_case  .= 'WHEN ? THEN ? ';
				$balance_due_case .= 'WHEN ? THEN ? ';
				
				$status_bindings[]  = $row['id'];
				$status_bindings[]  = $row['status'];

				$balance_due_bindings[] = $row['id'];
				$balance_due_bindings[] = $row['balance_due'];

				
			}

			$status_case  .= 'END';
			$balance_due_case .= 'END';

			$sql = "
				UPDATE invoices
				SET
					status = {$status_case},
					balance_due = {$balance_due_case},
					updated_at = ?
				WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
			";

			$bindings = array_merge(
				$status_bindings,
				$balance_due_bindings,
				[now()],
				$ids
			);

			FacadesDB::update($sql, $bindings);
		}
	}

}