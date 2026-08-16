<?php

namespace App\Services\Credit;

use App\Exceptions\CreditException;
use App\Helpers\Sanitize;
use App\Repositories\Credit\CreditRepository;
use Brick\Math\BigDecimal;
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
	private function ifAppliedAmountLessThanBalanceDue(int $company_id, array $applied) : bool {

		$ids = $this->getIds($applied);

		$invoices = $this->credit_repository->fetchMultipleInvoicesByIds($company_id, $ids);

		foreach($invoices as $invoice){
			foreach($applied as $ele){

				$ele['id'] = Sanitize::input($ele['id']);
				$ele['amount'] = Sanitize::input($ele['amount']);

				if((int) $ele['id'] === (int) $invoice->id){

					$invoice_balance_due = BigDecimal::of($invoice->balance_due);
					$applied_amount = BigDecimal::of($ele['amount']);

					if($applied_amount->isGreaterThan($invoice_balance_due)){
						return false;
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

		return $applied_sum->isLessThan($amount_left);

	}

	/**
	 * validateApplyUnapply function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateApplyUnapply(Request $request) : bool {

		$credit_id = (int) Sanitize::input($request->input('credit_id'));
		$company_id = (int) Sanitize::input($request->input('company_id'));
		$applied = $request->input('applied');

		$credit = $this->credit_repository->fetchById($company_id, $credit_id);

		if(!$credit){
			throw new CreditException('Invalid credit', 'invalid_credit', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedAmountLessThanBalanceDue($company_id, $applied)){
			throw new CreditException('Applied amount(s) are greater than balance due', 'applied_amount_greater_than_balance_due', (int) config('global.error_code'));
		}

		if(!$this->ifSumOfAppliedLessThanCreditLeft((string) $credit->amount_left_to_be_applied, $applied)){
			throw new CreditException('Applied amount(s) are greater than credit left', 'applied_amount_greater_than_credit_left', (int) config('global.error_code'));
		}

		return true;

	}

}