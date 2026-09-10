<?php

namespace App\Services\Invoice;

use App\Exceptions\CreditException;
use App\Helpers\Sanitize;
use App\Models\Invoice;
use App\Repositories\Invoice\InvoiceRepository;
use App\Services\Invoice\Exceptions\InvoiceException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;

/**
 * CreditApplyValidationService class
 */
class CreditApplyValidationService {

	public function __construct(
		private InvoiceRepository $invoice_repository
	){}

	/**
	 * getIds function
	 *
	 * @param array $applied
	 * @return array
	 */
	public function getIds(array $applied) : array {

		$ids = [];

		foreach($applied as $ele){
			$ele['id'] = Sanitize::input($ele['id']);
			if(!in_array($ele['id'], $ids)){
				array_push($ids, $ele['id']);
			}else{
				throw new InvoiceException('Duplicate entries found to apply this credit', 'duplicate_credit_ids', (int) config('global.error_code'));
			}
		}

		return $ids;

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
	 * ifAppliedCreditsAreFromSameClient function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param array $applied
	 * @return boolean
	 */
	private function ifAppliedCreditsAreFromSameClient(int $company_id, int $client_id, int $currency_id, array $applied) : bool {

		$ids = $this->getIds($applied);

		$counted = $this->invoice_repository->fetchCountForClientCreditsWithIds($company_id, $client_id, $currency_id, $ids);

		return (int) count($ids) === (int) $counted;

	}

	/**
	 * ifRemovedCreditsAreFromSameClient function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param array $removed_ids
	 * @return boolean
	 */
	private function ifRemovedCreditsAreFromSameClient(int $company_id, int $client_id, int $currency_id, array $removed_ids) : bool {

		$counted = $this->invoice_repository->fetchCountForClientCreditsWithIds($company_id, $client_id, $currency_id, $removed_ids);

		return (int) count($removed_ids) === (int) $counted;

	}

	/**
	 * ifAppliedAmountLessThanCreditLeft function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param array $applied
	 * @return boolean
	 */
	public function ifAppliedAmountLessThanCreditLeft(int $company_id, int $invoice_id, array $applied) : bool {

		$ids = $this->getIds($applied);

		$credits = $this->invoice_repository->fetchMultipleCreditsByIds($company_id, $ids);
		$ledger = $this->invoice_repository->fetchAppliedCreditsLedger($company_id, $invoice_id, $ids);

		foreach($credits as $credit){
			foreach($applied as $ele){

				$ele['id'] = Sanitize::input($ele['id']);
				$ele['amount'] = Sanitize::input($ele['amount']);

				if((int) $ele['id'] === (int) $credit->id){

					$already_applied = BigDecimal::of(0);

					foreach($ledger as $entry){
						if((int) $entry->credit_id === (int) $ele['id']){
							$already_applied = $already_applied->plus($entry->applied_credit);
							break;
						}
					}

					$allowed = BigDecimal::of($credit->amount_left_to_be_applied);
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
	 * @return boolean
	 */
	public function ifNoNegativesFound(array $rows) : bool {

		foreach($rows as $row){
			logger($row);
			if(!isset($row['id']) || !isset($row['amount']) || !isset($row['credit']) || !isset($row['left']) || !isset($row['show_text_input']) || !isset($row['total']) || !isset($row['type'])){
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
	 * validateApplyUnapply function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateApplyUnapply(Request $request) : bool {

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

		if(!$this->ifNoNegativesFound($applied)){
			
			throw new InvoiceException('Invalid request', 'invalid_request_nve_found', (int) config('global.error_code'));
		}

		if($this->removedIdInApplied($applied, $removed_ids)){
			throw new InvoiceException('Unexpected error : removed invoice exists in applied invoice', 'unexpected_error', (int) config('global.error_code'));
		}

		$invoice = $this->invoice_repository->fetchInvoiceObjById($invoice_id, $company_id, ['client_id', 'currency_id', 'total']);

		if(!$invoice){
			throw new InvoiceException('Invalid invoice', 'invalid_invoice', (int) config('global.error_code'));
		}

		if(!$this->ifRemovedCreditsAreFromSameClient($company_id, (int) $invoice->client_id, (int) $invoice->currency_id, $removed_ids)){
			throw new InvoiceException('Error : removal credits mismatch of client', 'client_removed_mismatch', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedCreditsAreFromSameClient($company_id, (int) $invoice->client_id, (int) $invoice->currency_id, $applied)){
			throw new InvoiceException('Client or currency mismatch to apply credit on invoice', 'client_currency_mismatch', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedAmountLessThanCreditLeft($company_id, $invoice_id, $applied)){
			throw new InvoiceException('Applied amount(s) are greater than credit left', 'applied_amount_greater_than_credit_left', (int) config('global.error_code'));
		}

		if(!$this->validateAppliedAgainstTotal((string) $invoice->total, $applied)){
			throw new InvoiceException('Applied amount(s) are greater than balance due', 'applied_amount_greater_than_balance_due', (int) config('global.error_code'));
		}

		return true;

	}

}