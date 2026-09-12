<?php

namespace App\Modules\ApplyUnapply\Common\Traits;

use App\Helpers\Sanitize;
use App\Models\InvoiceLedger;
use App\Services\Invoice\Exceptions\InvoiceException;
use Illuminate\Support\Facades\DB;

trait ApplyUnapplyCommon {

	/**
	 * getIds function
	 *
	 * @param array $applied
	 * @param string $exception
	 * @return array
	 */
	public function getIds(array $applied, string $exception = InvoiceException::class) : array {

		$ids = [];

		foreach($applied as $ele){
			$ele['id'] = Sanitize::input($ele['id']);
			if(!in_array($ele['id'], $ids)){
				array_push($ids, $ele['id']);
			}else{
				throw new $exception('Duplicate entries found to apply this entry', 'duplicate_entry_ids', (int) config('global.error_code'));
			}
		}

		return $ids;

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

			DB::update($sql, $bindings);
		}

	}


}