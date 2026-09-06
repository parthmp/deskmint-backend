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
	private function getIds(array $applied) : array {

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

	public function ifAppliedAmountLessThanCreditLeft(int $company_id, int $invoice_id, array $applied) : bool {

		$ids = $this->getIds($applied);

		$credits = $this->invoice_repository->fetchMultipleCreditsByIds($company_id, $ids);
		$ledger = $this->invoice_repository->fetchAppliedCreditsLedger($company_id, $invoice_id, $ids);

		foreach($credits as $credit){
			foreach($applied as $ele){

				$ele['id'] = Sanitize::input($ele['id']);
				$ele['amount'] = Sanitize::input($ele['amount']);

				if((int) $ele['id'] === (int) $credit->id){

					// $already_applied = BigDecimal::of(0);

					// foreach($ledger as $entry){
					// 	if((int) $entry->invoice_id === (int) $ele['id']){
					// 		$already_applied = $already_applied->plus($entry->applied_credit);
					// 		break;
					// 	}
					// }

					// $allowed = BigDecimal::of($invoice->balance_due);
					// $allowed = $allowed->plus($already_applied);
					// $applied_amount = BigDecimal::of($ele['amount']);
					
					// if($applied_amount->isGreaterThan($allowed)){
					// 	throw new CreditException('Amount '.$ele['amount'].' is greater than allowed amount', 'gt_allowed', (int) config('global.error_code'));
					// }
					
				}
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

		if($this->removedIdInApplied($applied, $removed_ids)){
			throw new InvoiceException('Unexpected error : removed invoice exists in applied invoice', 'unexpected_error', (int) config('global.error_code'));
		}

		$invoice = $this->invoice_repository->fetchInvoiceObjById($invoice_id, $company_id, ['client_id', 'currency_id', 'total']);

		if(!$invoice){
			throw new InvoiceException('Invalid invoice', 'invalid_invoice', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedCreditsAreFromSameClient($company_id, (int) $invoice->client_id, (int) $invoice->currency_id, $applied)){
			throw new InvoiceException('Client or currency mismatch to apply credit on invoice', 'client_currency_mismatch', (int) config('global.error_code'));
		}

		if(!$this->ifAppliedAmountLessThanCreditLeft($company_id, $invoice_id, $applied)){
			throw new InvoiceException('Applied amount(s) are greater than credit left', 'applied_amount_greater_than_credit_left', (int) config('global.error_code'));
		}

		// if(!$this->ifSumOfAppliedLessThanCreditLeft((string) $credit->amount, $applied)){
		// 	throw new InvoiceException('Applied amount(s) are greater than credit left', 'applied_amount_greater_than_credit_left', (int) config('global.error_code'));
		// }

		return true;

	}

}