<?php

namespace App\Modules\ApplyUnapply\Invoices;

use App\Helpers\Sanitize;
use App\Modules\ApplyUnapply\Common\DB\DB;
use App\Modules\ApplyUnapply\Common\Traits\ApplyUnapplyCommon;
use App\Services\Invoice\Exceptions\InvoiceException;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;

class Validation {

	use ApplyUnapplyCommon;

	public function __construct(
		private DB $db
	){}

	/**
	 * removedIdInApplied function
	 *
	 * @param array $applied
	 * @param array $removed
	 * @return boolean
	 */
	public function removedIdInApplied(array $applied, array $removed) : bool {
		$ids = $this->getIds($applied);
		foreach($ids as $applied_id){
			if(in_array($applied_id, $removed)){
				return true;
			}
		}
		return false;
	}


	/**
	 * ifAppliedEntriesAreFromSameClient function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param array $applied
	 * @param string $type
	 * @return boolean
	 */
	private function ifAppliedEntriesAreFromSameClient(int $company_id, int $client_id, int $currency_id, array $applied, string $type = 'credit') : bool {

		$ids = $this->getIds($applied);

		$counted = $this->db->fetchCountForClientEntriesWithIds($company_id, $client_id, $currency_id, $ids, $type);

		return (int) count($ids) === (int) $counted;

	}

	/**
	 * ifRemovedEntriesAreFromSameClient function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param array $removed_ids
	 * @param string $type
	 * @return boolean
	 */
	private function ifRemovedEntriesAreFromSameClient(int $company_id, int $client_id, int $currency_id, array $removed_ids, string $type = 'credit') : bool {

		$counted = $this->db->fetchCountForClientEntriesWithIds($company_id, $client_id, $currency_id, $removed_ids, $type);

		return (int) count($removed_ids) === (int) $counted;

	}

	/**
	 * ifAppliedAmountLessThanEntryLeft function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param string $type
	 * @param array $applied
	 * @return boolean
	 */
	public function ifAppliedAmountLessThanEntryLeft(int $company_id, int $invoice_id, string $type, array $applied) : bool {

		$ids = $this->getIds($applied);

		$credits_or_payments = $this->db->fetchMultipleEntriesByIds($company_id, $ids, $type);
		$ledger = $this->db->fetchAppliedEntriesLedger($company_id, $invoice_id, $ids, $type);
		$cp_id = null;

		foreach($credits_or_payments as $credit_or_payment){
			foreach($applied as $ele){

				$ele['id'] = Sanitize::input($ele['id']);
				$ele['amount'] = Sanitize::input($ele['amount']);

				if((int) $ele['id'] === (int) $credit_or_payment->id){

					$already_applied = BigDecimal::of(0);

					foreach($ledger as $entry){

						if($type === 'credit'){
							$cp_id = (int) $entry->credit_id;
						}else if($type === 'payment'){
							$cp_id = (int) $entry->payment_id;
						}

						if((int) $cp_id === (int) $ele['id']){
							$already_applied = $already_applied->plus($entry->applied_credit);
							break;
						}
					}

					$allowed = BigDecimal::of($credit_or_payment->amount_left_to_be_applied);
					$allowed = $allowed->plus($already_applied);
					$applied_amount = BigDecimal::of($ele['amount']);
					
					if($applied_amount->isGreaterThan($allowed)){
						throw new InvoiceException('Amount '.$ele['amount'].' is greater than allowed amount', 'gt_allowed', (int) config('global.error_code'));
					}
					
				}
			}
		}

		return true;

	}

	/**
	 * validateAppliedAgainstTotal function
	 *
	 * @param string $total
	 * @param array $applied
	 * @return boolean
	 */
	private function validateAppliedAgainstTotal(string $total, array $applied) : bool {

		$applied_sum = BigDecimal::of(0);
		$total_amount = BigDecimal::of($total);

		foreach($applied as $ele){

			$applied_amount = Sanitize::input($ele['amount']);
			$applied_amount = BigDecimal::of($applied_amount);

			$applied_sum = $applied_sum->plus($applied_amount);

		}

		return !$applied_sum->isGreaterThan($total_amount);

	}

	/**
	 * ifNoNegativesFound function
	 *
	 * @param array $rows
	 * @param string $type
	 * @return boolean
	 */
	public function ifNoNegativesFound(array $rows, string $type) : bool {

		foreach($rows as $row){
			
			if(!isset($row['id']) || !isset($row['amount']) || !isset($row[$type]) || !isset($row['left']) || !isset($row['show_text_input']) || !isset($row['total']) || !isset($row['type'])){
				throw new InvoiceException('Invalid request', 'invalid_request', (int) config('global.error_code'));
			}

			$str = (string) $row['amount'];
			$is_number = (bool) preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $str);

			if(!$is_number){
				throw new InvoiceException('Invalid request', 'invalid_request_non_numeric', (int) config('global.error_code'));
			}

			$amount = BigDecimal::of($row['amount']);

			if($amount->isLessThan(BigDecimal::of(0)) || $amount->isEqualTo(BigDecimal::of(0))){
				return false;
			}

		}

		return true;
		
	}

	/**
	 * validateApplyUnapplyForInvoice function
	 *
	 * @param Request $request
	 * @param string $type
	 * @return boolean
	 */
	public function validateApplyUnapplyForInvoice(Request $request, string $type) : bool {

		if(!$request->has('invoice_id') || !$request->has('company_id') || !$request->has('applied') || !$request->has('removed_ids')){
			throw new InvoiceException('Invalid request', 'invalid_request', (int) config('global.error_code'));
		}

		$invoice_id = (int) Sanitize::input($request->input('invoice_id'));
		$company_id = (int) Sanitize::input($request->input('company_id'));
		$applied = $request->input('applied');
		$removed_ids = $request->input('removed_ids');

		if(!is_array($applied)){
			throw new InvoiceException('Invalid request', 'invalid_request', (int) config('global.error_code'));
		}

		if(!is_array($removed_ids)){
			throw new InvoiceException('Invalid request', 'invalid_request', (int) config('global.error_code'));
		}

		if(!$this->ifNoNegativesFound($applied, $type)){
			
			throw new InvoiceException('Invalid request', 'invalid_request_nve_found', (int) config('global.error_code'));
		}

		if($this->removedIdInApplied($applied, $removed_ids)){
			throw new InvoiceException('Unexpected error : removed invoice exists in applied invoice', 'unexpected_error', (int) config('global.error_code'));
		}

		$invoice = $this->db->fetchInvoiceObjById($invoice_id, $company_id, ['client_id', 'currency_id', 'total']);

		if(!$invoice){
			throw new InvoiceException('Invalid invoice', 'invalid_invoice', (int) config('global.error_code'));
		}

		if(!$this->ifRemovedEntriesAreFromSameClient($company_id, (int) $invoice->client_id, (int) $invoice->currency_id, $removed_ids, $type)){
			throw new InvoiceException('Error : removal credits mismatch of client', 'client_removed_mismatch', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedEntriesAreFromSameClient($company_id, (int) $invoice->client_id, (int) $invoice->currency_id, $applied, $type)){
			throw new InvoiceException('Client or currency mismatch to apply credit on invoice', 'client_currency_mismatch', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedAmountLessThanEntryLeft($company_id, $invoice_id, $type, $applied)){
			throw new InvoiceException('Applied amount(s) are greater than credit left', 'applied_amount_greater_than_credit_left', (int) config('global.error_code'));
		}

		if(!$this->validateAppliedAgainstTotal((string) $invoice->total, $applied)){
			throw new InvoiceException('Applied amount(s) are greater than balance due', 'applied_amount_greater_than_balance_due', (int) config('global.error_code'));
		}

		return true;

	}

}