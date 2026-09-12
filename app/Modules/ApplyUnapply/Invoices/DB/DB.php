<?php

namespace App\Modules\ApplyUnapply\Invoices\DB;

use App\Enums\Credits\CreditStatus;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceLedger;
use App\Models\Payment;
use App\Modules\Payment\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB as FacadesDB;

class DB {

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

	/**
	 * fetchAlreadyAppliedEntries function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param string $type
	 * @return array
	 */
	public function fetchAlreadyAppliedEntries(int $company_id, int $invoice_id, int $currency_id, int $client_id, string $type = 'credit') : array {

		$model = Credit::class;
		if($type === 'payment'){
			$model = Payment::class;
		}
		
		$applied_credits = $model::select($type.'s'.'.id as id', $type.'s.'.$type.'_number as '.$type, $type.'s.amount as total', 'il.applied_amount_from_'.$type.'s as amount', $type.'s.amount_left_to_be_applied as left')
			->join('invoice_ledger as il', 'il.'.$type.'_id', '=', $type.'s.id')
			->where([[$type.'s.currency_id', '=', $currency_id], [$type.'s.company_id', '=', $company_id], [$type.'s.client_id', '=', $client_id], ['il.invoice_id', '=', $invoice_id]])
			->get()->toArray();

		return $applied_credits;

	}

	
	/**
	 * fetchLedgerEntries function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param array $not_in_ids
	 * @param string $type
	 * @return array
	 */
	public function fetchLedgerEntries(int $company_id, int $invoice_id, array $not_in_ids, string $type) : array {
		$entries = InvoiceLedger::select('invoice_id as invoice_id', $type.'_id as '.$type.'_id', 'applied_amount_from_'.$type.'s as applied_amount')->where([['company_id', '=', $company_id], ['invoice_id', '=', $invoice_id], [$type.'_id', '<>', null]])->whereNotIn($type.'_id', $not_in_ids)->get()->toArray();
		return $entries;
	}

	/**
	 * fetchEntriesForApplyUnapply function
	 *
	 * @param integer $company_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param integer $invoice_id
	 * @param string $searched
	 * @param array $locally_applied_ids
	 * @param array $fully_applied_ids
	 * @param string $type
	 * @return array
	 */
	public function fetchEntriesForApplyUnapply(int $company_id, int $currency_id, int $client_id, int $invoice_id, string $searched, array $locally_applied_ids, array $fully_applied_ids, string $type) : array {
		
		$model = Credit::class;
		$applied_status = CreditStatus::APPLIED->value;
		$partially_applied_status = CreditStatus::PARTIALLY_APPLIED->value;
		$not_applied_status = CreditStatus::NOT_APPLIED->value;

		if($type === 'payment'){
			$model = Payment::class;
			$applied_status = PaymentStatus::APPLIED->value;
			$partially_applied_status = PaymentStatus::PARTIALLY_APPLIED->value;
			$not_applied_status = PaymentStatus::NOT_APPLIED->value;
		}

		$not_fully_applied_entries_raw = $model::select('id as id', $type.'_number as '.$type, 'amount as total', 'amount_left_to_be_applied as left')->where([['currency_id', '=', $currency_id], ['company_id', '=', $company_id], ['client_id', '=', $client_id]])
			->whereNotIn('id', $locally_applied_ids)
			->where(function($q) use ($not_applied_status, $partially_applied_status) {
				$q->where('status', '=', $not_applied_status)
				->orWhere('status', '=', $partially_applied_status);
			})->when($searched, function($query, $searched) use ($type) {
				
				$query->where(function($q) use ($searched, $type) {

					$q->where($type.'_number', 'like', "%{$searched}%")
					->orWhere('id', 'like', "%{$searched}%")
					->orWhere('amount', 'like', "%{$searched}%")
					->orWhere('applied_amount', 'like', "%{$searched}%")
					->orWhere('amount_left_to_be_applied', 'like', "%{$searched}%");
				});
			})
			->orderBy('id', 'desc')->limit(50)->get()->toArray();
		
		$applied_entries = [];
		
		if(!empty($fully_applied_ids)){
			
			$applied_entries = $model::select($type.'s.id as id', $type.'s.'.$type.'_number as '.$type, $type.'s.amount as total', 'il.applied_amount_from_'.$type.'s as applied_amount', $type.'s.amount_left_to_be_applied as left')
			->join('invoice_ledger as il', 'il.'.$type.'_id', '=', $type.'s.id')
			->where([[$type.'s.currency_id', '=', $currency_id], [$type.'s.company_id', '=', $company_id], [$type.'s.client_id', '=', $client_id], ['il.invoice_id', '=', $invoice_id]])
			->whereIn($type.'s.id', $fully_applied_ids)
			->where($type.'s.status', '=', $applied_status)->when($searched, function($query, $searched) use ($type) {
				$query->where(function ($q) use ($searched, $type) {

					$q->where($type.'s.'.$type.'_number', 'like', "%{$searched}%")
					->orWhere($type.'s.id', 'like', "%{$searched}%")
					->orWhere($type.'s.amount', 'like', "%{$searched}%")
					->orWhere($type.'s.applied_amount', 'like', "%{$searched}%")
					->orWhere('il.applied_amount_from_'.$type.'s', 'like', "%{$searched}%");
				});
			})
			->orderBy($type.'s.id', 'desc')->limit(50)->get()->toArray();

		}

		$entries = $this->fetchLedgerEntries($company_id, $invoice_id, $fully_applied_ids, $type);

		$not_fully_applied_entries = [];
		foreach($not_fully_applied_entries_raw as $not_fully_applied_entry_raw){
			$temp = $not_fully_applied_entry_raw;
			$temp['applied_amount'] = '';
			foreach($entries as $entry){
				if((int) $entry[$type.'_id'] === (int) $not_fully_applied_entry_raw['id']){
					$temp['applied_amount'] = $entry['applied_amount'];
					break;
				}
			}
			$not_fully_applied_entries[] = $temp;
		}

		return [
			'not_fully_applied_entries'	=>	$not_fully_applied_entries,
			'applied_entries'			=>	$applied_entries
		];

	}

	/**
	 * fetchCountForClientEntriesWithIds function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param array $ids
	 * @param string $type
	 * @return integer
	 */
	public function fetchCountForClientEntriesWithIds(int $company_id, int $client_id, int $currency_id, array $ids, string $type) : int {
		$model = Credit::class;
		if($type === 'payment'){
			$model = Payment::class;
		}
		return $model::where([['company_id', '=', $company_id], ['client_id', '=', $client_id], ['currency_id', '=', $currency_id]])->whereIn('id', $ids)->count();

	}

	/**
	 * fetchMultipleEntriesByIds function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @param string $type
	 * @param array $selects
	 * @return Collection
	 */
	public function fetchMultipleEntriesByIds(int $company_id, array $ids, string $type, array $selects = ['*']) : Collection {
		$model = Credit::class;
		if($type === 'payment'){
			$model = Payment::class;
		}
		return $model::select(...$selects)->where('company_id', '=', $company_id)->whereIn('id', $ids)->get();
	}

	/**
	 * fetchAppliedEntriesLedger function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param array $entry_ids
	 * @param string $type
	 * @return Collection
	 */
	public function fetchAppliedEntriesLedger(int $company_id, int $invoice_id, array $entry_ids, string $type) : Collection {
		return InvoiceLedger::select('id as ledger_id', 'applied_amount_from_'.$type.'s as applied_'.$type, 'invoice_id as invoice_id', $type.'_id as '.$type.'_id')->where([['company_id', '=', $company_id], ['invoice_id', '=', $invoice_id]])->whereIn($type.'_id', $entry_ids)->get();
	}


	/**
	 * fetchInvoiceObjById function
	 *
	 * @param integer $invoice_id
	 * @param integer $company_id
	 * @param array $selects
	 * @return Invoice|null
	 */
	public function fetchInvoiceObjById(int $invoice_id, int $company_id, array $selects = ['*']) : ?Invoice {
		return Invoice::select(...$selects)->where([['id', '=', $invoice_id], ['company_id', '=', $company_id]])->first();
	}

	
	/**
	 * removeLedgerEntries function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param array $ids
	 * @param string $type
	 * @return void
	 */
	public function removeLedgerEntries(int $company_id, int $invoice_id, array $ids, string $type) : void {
		InvoiceLedger::where([['company_id', '=', $company_id], ['invoice_id', '=', $invoice_id]])->whereIn($type.'_id', $ids)->forceDelete();
	}

	/**
	 * fetchLedgerEntriesForApply function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param string $type
	 * @return array
	 */
	public function fetchLedgerEntriesForApply(int $company_id, int $invoice_id, string $type) : array {

		$conditions = [['company_id', '=', $company_id], ['invoice_id', '=', $invoice_id]];
		$conditions[] = [$type.'_id', '<>', null];
		
		return InvoiceLedger::where($conditions)->get()->toArray();

	}

	
	/**
	 * insertNewLedgerEntries function
	 *
	 * @param array $chunks
	 * @return void
	 */
	public function insertNewLedgerEntries(array $chunks) : void {
		foreach($chunks as $chunk){
			InvoiceLedger::insert($chunk);
		}
	}


	/**
	 * updateLedgerEntries function
	 *
	 * @param array $chunks
	 * @return void
	 */
	public function updateLedgerEntries(array $chunks) : void {

		foreach($chunks as $chunk){

			$ids = array_column($chunk, 'id');

			$credits_case  = 'CASE id ';
			$payments_case = 'CASE id ';
			$total_case    = 'CASE id ';

			$credits_bindings  = [];
			$payments_bindings = [];
			$total_bindings    = [];

			foreach($chunk as $row){

				$credits_case  .= 'WHEN ? THEN ? ';
				$payments_case .= 'WHEN ? THEN ? ';
				$total_case    .= 'WHEN ? THEN ? ';

				$credits_bindings[]  = $row['id'];
				$credits_bindings[]  = $row['applied_amount_from_credits'];

				$payments_bindings[] = $row['id'];
				$payments_bindings[] = $row['applied_amount_from_payments'];

				$total_bindings[]    = $row['id'];
				$total_bindings[]    = $row['total_applied'];
			}

			$credits_case  .= 'END';
			$payments_case .= 'END';
			$total_case    .= 'END';

			$sql = "
				UPDATE invoice_ledger
				SET
					applied_amount_from_credits = {$credits_case},
					applied_amount_from_payments = {$payments_case},
					total_applied = {$total_case},
					updated_at = ?
				WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
			";

			$bindings = array_merge(
				$credits_bindings,
				$payments_bindings,
				$total_bindings,
				[now()],
				$ids
			);

			FacadesDB::update($sql, $bindings);
		}

	}

	/**
	 * fetchMultipleLedgerOfIds function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @param string $type
	 * @return array
	 */
	public function fetchMultipleLedgerOfIds(int $company_id, array $ids, string $type) : array {

		$key = $type.'_id';

		return InvoiceLedger::where('company_id', '=', $company_id)->whereIn($key, $ids)->orderBy($key, 'asc')->get()->toArray();

	}

	
	/**
	 * fetchMultipleEntriesByIdsForApply function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @param string $type
	 * @return array
	 */
	public function fetchMultipleEntriesByIdsForApply(int $company_id, array $ids, string $type) : array {
		$model = Credit::class;
		if($type === 'payment'){
			$model = Payment::class;
		}
		return $model::where('company_id', '=', $company_id)->whereIn('id', $ids)->orderBy('id', 'asc')->get()->toArray();
	}


	/**
	 * updateMultipleEntries function
	 *
	 * @param array $chunks
	 * @param string $type
	 * @return void
	 */
	public function updateMultipleEntries(array $chunks, $type) : void {

		$table = $type.'s';

		foreach($chunks as $chunk){

			$ids = array_column($chunk, 'id');

			$status_case  = 'CASE id ';
			$applied_amount_case = 'CASE id ';
			$amount_left_to_be_applied_case    = 'CASE id ';

			$status_bindings  = [];
			$applied_amount_bindings = [];
			$amount_left_to_be_applied_bindings = [];

			foreach($chunk as $row){

				$status_case  .= 'WHEN ? THEN ? ';
				$applied_amount_case .= 'WHEN ? THEN ? ';
				$amount_left_to_be_applied_case    .= 'WHEN ? THEN ? ';

				$status_bindings[]  = $row['id'];
				$status_bindings[]  = $row['status'];

				$applied_amount_bindings[] = $row['id'];
				$applied_amount_bindings[] = $row['applied_amount'];

				$amount_left_to_be_applied_bindings[] = $row['id'];
				$amount_left_to_be_applied_bindings[] = $row['amount_left_to_be_applied'];
			}

			$status_case  .= 'END';
			$applied_amount_case .= 'END';
			$amount_left_to_be_applied_case .= 'END';

			$sql = "
				UPDATE ".$table."
				SET
					status = {$status_case},
					applied_amount = {$applied_amount_case},
					amount_left_to_be_applied = {$amount_left_to_be_applied_case},
					updated_at = ?
				WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
			";

			$bindings = array_merge(
				$status_bindings,
				$applied_amount_bindings,
				$amount_left_to_be_applied_bindings,
				[now()],
				$ids
			);

			FacadesDB::update($sql, $bindings);
		}
	}

	
	/**
	 * fetchLedgerForApplying function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchLedgerForApplying(int $company_id, int $invoice_id) : array {
		return InvoiceLedger::select('total_applied', 'invoice_id')->where([['company_id', '=', $company_id], ['invoice_id', '=', $invoice_id]])->get()->toArray();
	}

	/**
	 * updateInvoiceForApply function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param array $update
	 * @return void
	 */
	public function updateInvoiceForApply(int $company_id, int $invoice_id, array $update) : void {
		Invoice::where([['company_id', '=', $company_id], ['id', '=', $invoice_id]])->update([
			'status' 		=> $update['status'],
			'balance_due' 	=> $update['balance_due']
		]);
	}
	
}