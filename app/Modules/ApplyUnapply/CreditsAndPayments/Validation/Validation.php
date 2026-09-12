<?php

namespace App\Modules\ApplyUnapply\CreditsAndPayments\Validation;

use App\Exceptions\CreditException;
use App\Exceptions\PaymentException;
use App\Helpers\Sanitize;
use App\Models\Credit;
use App\Modules\ApplyUnapply\Common\Traits\ApplyUnapplyCommon;
use App\Modules\ApplyUnapply\CreditsAndPayments\DB\DB;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;

/**
 * Validation class
 */
class Validation {

	use ApplyUnapplyCommon;

	public function __construct(
		private DB $db
	){}


	/**
	 * ifAppliedAmountLessThanBalanceDue function
	 *
	 * @param integer $company_id
	 * @param integer $entry_id
	 * @param array $applied
	 * @param string $exception
	 * @param string $type
	 * @return boolean
	 */
	private function ifAppliedAmountLessThanBalanceDue(int $company_id, int $entry_id, array $applied, string $exception, string $type) : bool {

		$ids = $this->getIds($applied, $exception);

		$invoices = $this->db->fetchMultipleInvoicesByIds($company_id, $ids);
		$ledger = $this->db->fetchAppliedEntriesLedger($company_id, $entry_id, $ids, $type);

		foreach($invoices as $invoice){
			foreach($applied as $ele){

				$ele['id'] = Sanitize::input($ele['id']);
				$ele['amount'] = Sanitize::input($ele['amount']);

				if((int) $ele['id'] === (int) $invoice->id){

					$already_applied = BigDecimal::of(0);

					foreach($ledger as $entry){
						if((int) $entry->invoice_id === (int) $ele['id']){
							$already_applied = $already_applied->plus($entry->{'applied_'.$type});
							break;
						}
					}

					$allowed = BigDecimal::of($invoice->balance_due);
					$allowed = $allowed->plus($already_applied);
					$applied_amount = BigDecimal::of($ele['amount']);
					
					if($applied_amount->isGreaterThan($allowed)){
						throw new $exception('Amount '.$ele['amount'].' is greater than allowed amount', 'gt_allowed', (int) config('global.error_code'));
					}
					
				}
			}
		}

		return true;

	}

	/**
	 * ifSumOfAppliedLessThanCreditLeft function
	 *
	 * @param string $left_amount
	 * @param array $applied
	 * @return boolean
	 */
	private function ifSumOfAppliedLessThanCreditLeft(string $left_amount, array $applied) : bool {

		$applied_sum = BigDecimal::of(0);
		$amount_left = BigDecimal::of($left_amount);

		foreach($applied as $ele){

			$applied_amount = Sanitize::input($ele['amount']);
			$applied_amount = BigDecimal::of($applied_amount);

			$applied_sum = $applied_sum->plus($applied_amount);

		}
		if($applied_sum->isEqualTo($amount_left)){
			return true;
		}
		
		return $applied_sum->isLessThan($amount_left);

	}

	/**
	 * ifAppliedInvoicesAreFromSameClient function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param array $applied
	 * @param string $exception
	 * @return boolean
	 */
	private function ifAppliedInvoicesAreFromSameClient(int $company_id, int $client_id, int $currency_id, array $applied, string $exception) : bool {

		$ids = $this->getIds($applied, $exception);

		$counted = $this->db->fetchCountForClientInvoicesWithIds($company_id, $client_id, $currency_id, $ids);

		return (int) count($ids) === (int) $counted;

	}

	/**
	 * removedIdInApplied function
	 *
	 * @param array $applied
	 * @param array $removed
	 * @param string $exception
	 * @return boolean
	 */
	public function removedIdInApplied(array $applied, array $removed, string $exception) : bool {
		$ids = $this->getIds($applied, $exception);
		foreach($ids as $applied_id){
			if(in_array($applied_id, $removed)){
				return true;
			}
		}
		return false;
	}

	/**
	 * ifNoNegativesFound function
	 *
	 * @param array $rows
	 * @param string $exception
	 * @return boolean
	 */
	public function ifNoNegativesFound(array $rows, string $exception) : bool {

		foreach($rows as $row){
			
			if(!isset($row['id']) || !isset($row['amount'])|| !isset($row['due']) || !isset($row['show_text_input']) || !isset($row['total']) || !isset($row['type'])){
				throw new $exception('Invalid request', 'invalid_request', (int) config('global.error_code'));
			}

			$str = (string) $row['amount'];
			$is_number = (bool) preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $str);

			if(!$is_number){
				throw new $exception('Invalid request', 'invalid_request_non_numeric', (int) config('global.error_code'));
			}

			$amount = BigDecimal::of($row['amount']);

			if($amount->isLessThan(BigDecimal::of(0)) || $amount->isEqualTo(BigDecimal::of(0))){
				return false;
			}

		}

		return true;
		
	}

	/**
	 * validateApplyUnapply function
	 *
	 * @param Request $request
	 * @param string $type
	 * @return boolean
	 */
	public function validateApplyUnapply(Request $request, string $type) : bool {

		$exception = CreditException::class;
		if($type === 'payment'){
			$exception = PaymentException::class;
		}

		if(!$request->has($type.'_id') || !$request->has('company_id') || !$request->has('applied') || !$request->has('removed_ids')){
			throw new $exception('Invalid request', 'invalid_request', (int) config('global.error_code'));
		}

		$entry_id = (int) Sanitize::input($request->input($type.'_id'));
		$company_id = (int) Sanitize::input($request->input('company_id'));
		$applied = $request->input('applied');
		$removed_ids = $request->input('removed_ids');

		

		if(!is_array($applied)){
			throw new $exception('Invalid request', 'invalid_request', (int) config('global.error_code'));
		}

		if(!is_array($removed_ids)){
			throw new $exception('Invalid request', 'invalid_request', (int) config('global.error_code'));
		}

		if(!$this->ifNoNegativesFound($applied, $type)){
			throw new $exception('Invalid request', 'invalid_request_nve_found', (int) config('global.error_code'));
		}


		if($this->removedIdInApplied($applied, $removed_ids, $exception)){
			throw new $exception('Unexpected error : removed invoice exists in applied invoice', 'unexpected_error', (int) config('global.error_code'));
		}

		$entry = $this->db->fetchById($company_id, $entry_id, $type);

		if(!$entry){
			throw new $exception('Invalid '.$type, 'invalid_'.$type, (int) config('global.error_code'));
		}

		if(!$this->ifAppliedInvoicesAreFromSameClient($company_id, (int) $entry->client_id, (int) $entry->currency_id, $applied, $exception)){
			throw new $exception('Client or currency mismatch to apply '.$type.' on invoice(s)', 'client_currency_mismatch', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedAmountLessThanBalanceDue($company_id, $entry_id, $applied, $exception, $type)){
			throw new $exception('Applied amount(s) are greater than balance due', 'applied_amount_greater_than_balance_due', (int) config('global.error_code'));
		}

		if(!$this->ifSumOfAppliedLessThanCreditLeft((string) $entry->amount, $applied)){
			throw new $exception('Applied amount(s) are greater than '.$type.' left', 'applied_amount_greater_than_credit_left', (int) config('global.error_code'));
		}

		return true;

	}

}