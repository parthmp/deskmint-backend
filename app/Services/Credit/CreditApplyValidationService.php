<?php

namespace App\Services\Credit;

use App\Exceptions\CreditException;
use App\Helpers\Sanitize;
use App\Repositories\Credit\CreditRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;

/**
 * CreditApplyValidationService class
 */
class CreditApplyValidationService {

	public function __construct(
		private CreditRepository $credit_repository
	){}

	/**
	 * getIds function
	 *
	 * @param array $applied
	 * @return array
	 */
	private function getIds(array $applied) : array {

		$ids = [];

		foreach($applied as $ele){
			$ele['id'] = Sanitize::input($ele['id']);
			if(!in_array($ele['id'], $ids)){
				array_push($ids, $ele['id']);
			}else{
				throw new CreditException('Duplicate entries found to apply this credit', 'duplicate_invoice_ids', (int) config('global.error_code'));
			}
		}

		return $ids;

	}

	/**
	 * ifAppliedAmountLessThanBalanceDue function
	 *
	 * @param integer $company_id
	 * @param array $applied
	 * @return boolean
	 */
	private function ifAppliedAmountLessThanBalanceDue(int $company_id, int $credit_id, array $applied) : bool {

		$ids = $this->getIds($applied);

		$invoices = $this->credit_repository->fetchMultipleInvoicesByIds($company_id, $ids);
		$ledger = $this->credit_repository->fetchAppliedCreditsLedger($company_id, $credit_id, $ids);

		foreach($invoices as $invoice){
			foreach($applied as $ele){

				$ele['id'] = Sanitize::input($ele['id']);
				$ele['amount'] = Sanitize::input($ele['amount']);

				if((int) $ele['id'] === (int) $invoice->id){

					$already_applied = BigDecimal::of(0);

					foreach($ledger as $entry){
						if((int) $entry->invoice_id === (int) $ele['id']){
							$already_applied = $already_applied->plus($entry->applied_credit);
							break;
						}
					}

					$allowed = BigDecimal::of($invoice->balance_due);
					$allowed = $allowed->plus($already_applied);
					$applied_amount = BigDecimal::of($ele['amount']);
					
					if($applied_amount->isGreaterThan($allowed)){
						throw new CreditException('Amount '.$ele['amount'].' is greater than allowed amount', 'gt_allowed', (int) config('global.error_code'));
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
	 * @param array $applied
	 * @return boolean
	 */
	private function ifAppliedInvoicesAreFromSameClient(int $company_id, int $client_id, int $currency_id, array $applied) : bool {

		$ids = $this->getIds($applied);

		$counted = $this->credit_repository->fetchCountForClientInvoicesWithIds($company_id, $client_id, $currency_id, $ids);

		return (int) count($ids) === (int) $counted;

	}

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
	 * validateApplyUnapply function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateApplyUnapply(Request $request) : bool {

		if(!$request->has('credit_id') || !$request->has('company_id') || !$request->has('applied') || !$request->has('removed_ids')){
			throw new CreditException('Invalid request', 'invalid_request', (int) config('global.error_code'));
		}

		$credit_id = (int) Sanitize::input($request->input('credit_id'));
		$company_id = (int) Sanitize::input($request->input('company_id'));
		$applied = $request->input('applied');
		$removed_ids = $request->input('removed_ids');

		if($this->removedIdInApplied($applied, $removed_ids)){
			throw new CreditException('Unexpected error : removed invoice exists in applied invoice', 'unexpected_error', (int) config('global.error_code'));
		}

		$credit = $this->credit_repository->fetchById($company_id, $credit_id);

		if(!$credit){
			throw new CreditException('Invalid credit', 'invalid_credit', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedInvoicesAreFromSameClient($company_id, (int) $credit->client_id, (int) $credit->currency_id, $applied)){
			throw new CreditException('Client or currency mismatch to apply credit on invoice(s)', 'client_currency_mismatch', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedAmountLessThanBalanceDue($company_id, $credit_id, $applied)){
			throw new CreditException('Applied amount(s) are greater than balance due', 'applied_amount_greater_than_balance_due', (int) config('global.error_code'));
		}

		if(!$this->ifSumOfAppliedLessThanCreditLeft((string) $credit->amount, $applied)){
			throw new CreditException('Applied amount(s) are greater than credit left', 'applied_amount_greater_than_credit_left', (int) config('global.error_code'));
		}

		return true;

	}

}