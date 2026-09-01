<?php

namespace App\Services\Credit;

use App\Enums\Credits\CreditStatus;
use App\Exceptions\CreditException;
use App\Helpers\Sanitize;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\GenerateInvoiceSnapshotJob;
use App\Models\Credit;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Repositories\Credit\CreditRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * CreditService class
 */
class CreditService {

	public function __construct(
		private CreditRepository $credit_repository,
		private EasyIndex $easy_index
	){}

	/**
	 * create function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param string $amount
	 * @param string $credit_number
	 * @return Credit
	 */
	public function create(int $company_id, int $client_id, string $amount, string $credit_number) : Credit {

		$currency_id = $this->credit_repository->fetchClientCurrencyId($company_id, $client_id);
		return $this->credit_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, '0.00', $amount, CreditStatus::NOT_APPLIED->value, $credit_number);

	}

	/**
	 * fetchIndex function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchIndex(Request $request) : array {
		$joins = [
					[
						'table' => 'clients',
						'first' => 'clients.id',
						'operator' => '=',
						'second' => 'credits.client_id',
						'columns' => ['clients.full_name as full_name']
					],
					
					[
						'table' => 'currencies',
						'first' => 'currencies.id',
						'operator' => '=',
						'second' => 'credits.currency_id',
						'columns' => ['currencies.code as c_code']
					],
				];

			$default_columns = [
				'searchable_columns'	=>	['clients.full_name', 'currencies.code', 'credits.status', 'credits.amount', 'credits.applied_amount', 'credits.amount_left_to_be_applied'],
				'searchable_dates'		=>	['clients.created_at'],
				'show_columns'			=>	[
					[
						'label'	=>	'full_name',
						'text'	=>	'Name',
					],
					[
						'label'	=>	'c_code',
						'text'	=>	'Currency',
					],
					[
						'label'	=>	'status',
	 					'text'	=>	'Status',
					],
					[
						'label'	=>	'amount',
	 					'text'	=>	'Amount',
					],
					[
						'label'	=>	'applied_amount',
	 					'text'	=>	'Applied',
					],
					[
						'label'	=>	'amount_left_to_be_applied',
	 					'text'	=>	'Amount left',
					],
					[
						'label'	=>	'created_at',
	 					'text'	=>	'Added on',
					]
				],
			];

			$rewrites = [
				'data' => [
					
					'credits.status' => [
						CreditStatus::NOT_APPLIED->value			=>	CreditStatus::NOT_APPLIED->label(),
						CreditStatus::PARTIALLY_APPLIED->value		=>	CreditStatus::PARTIALLY_APPLIED->label(),
						CreditStatus::APPLIED->value				=>	CreditStatus::APPLIED->label()
					]
				],
				'ui'	=>	[
					'status'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	CreditStatus::NOT_APPLIED->label(),
							'value'		=>	CreditStatus::NOT_APPLIED->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	CreditStatus::PARTIALLY_APPLIED->label(),
							'value'		=>	CreditStatus::PARTIALLY_APPLIED->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	CreditStatus::APPLIED->label(),
							'value'		=>	CreditStatus::APPLIED->value
						]
					]
				]

			];

		return $this->easy_index->setType('credit')->setJoins($joins)->setExceptionClass(CreditException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
			'c_code'						=>		'currencies.code',
			'full_name'						=>		'clients.full_name'
		 ])->setRewrites($rewrites)->setModel(Credit::class)->fetchIndex();

	}

	/**
	 * deleteCredits function
	 *
	 * @param array $ids
	 * @return boolean
	 */
	public function deleteCredits(array $ids) : bool {

		if($this->credit_repository->ifAnyCreditsAreApplied($ids)){
			
			$message = 'Can not delete : One of the credits are applied to an invoice.';
			if((int) count($ids) === 1){
				$message = 'Can not delete : Credit is applied to an invoice.';
			}

			throw new CreditException($message, 'blocked_applied_credit', (int) config('global.error_code'));

		}

		$del_response = $this->credit_repository->deleteMultipleCredits($ids);

		if(!$del_response){
			throw new Exception();
		}

		return $del_response;

	}

	/**
	 * fetchCredit function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return array
	 */
	public function fetchCreditForEdit(int $company_id, int $id) : array {
		return $this->credit_repository->fetchCreditForEdit($company_id, $id);
	}

	/**
	 * update function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param string $amount
	 * @param string $credit_number
	 * @param integer $credit_id
	 * @return Credit
	 */
	public function update(int $company_id, int $client_id, string $amount, string $credit_number, int $credit_id) : Credit {

		return DB::transaction(function () use ($company_id, $client_id, $amount, $credit_id, $credit_number) {

			//check access first.
			$credit = $this->credit_repository->fetchById($company_id, $credit_id);

			if(!$credit){
				throw new CreditException('Invalid credit provided', 'invalid_credit', (int) config('global.error_code'));
			}

			$currency_id = $this->credit_repository->fetchClientCurrencyId($company_id, $client_id);

			if((int) $credit->status === CreditStatus::NOT_APPLIED->value){
				//update it fully.
				return $this->credit_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, '0.00', $amount, CreditStatus::NOT_APPLIED->value, $credit_number, $credit_id);
			}

			//else check for further.
			//client should not be changed.
			//currency should not be changed.
			//amount must not be lower than applied amount.
			//update.

			if((int) $client_id !== (int) $credit->client_id){
				throw new CreditException('You can not change client for this entry', 'unable_to_change_client', (int) config('global.error_code'));
			}

			if((int) $currency_id !== (int) $credit->currency_id){
				throw new CreditException('You can not change currency for this entry', 'unable_to_change_currency', (int) config('global.error_code'));
			}

			$applied_amount = BigDecimal::of($credit->applied_amount);
			$amount = BigDecimal::of($amount);

			if($amount->isLessThan($applied_amount)){
				throw new CreditException('You can not update it for amount less than '.$credit->applied_amount, 'unable_to_update_invalid_amount', (int) config('global.error_code'));
			}

			$left_to_apply = $amount->minus($applied_amount);

			$status = CreditStatus::PARTIALLY_APPLIED->value;

			if($left_to_apply->isEqualTo(BigDecimal::of(0))){
				$status = CreditStatus::APPLIED->value;
			}

			$left_to_apply = $left_to_apply->toScale(2, RoundingMode::HalfUp)->__toString();
			return $this->credit_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, $credit->applied_amount, $left_to_apply, $status, $credit_number, $credit_id);

		});

	}

	/**
	 * fetchAppliedCreditInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @return array
	 */
	public function fetchAppliedCreditInvoices(int $company_id, int $credit_id) : array {
		return $this->credit_repository->fetchAppliedCreditInvoices($company_id, $credit_id);
	}

	/**
	 * fetchCreditWithCurrencyInfo function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @return array
	 */
	public function fetchCreditWithCurrencyInfo(int $company_id, int $credit_id) : array {
		return $this->credit_repository->fetchCreditWithCurrencyInfo($company_id, $credit_id);
	}

	/**
	 * searchInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param integer $credit_id
	 * @param array $applied_ids
	 * @param array $paid_ids
	 * @param string $searched
	 * @return array
	 */
	public function searchInvoices(int $company_id, int $currency_id, int $client_id, int $credit_id, array $applied_ids, array $paid_ids, string $searched) : array {

		// $already_applied = $this->credit_repository->fetchAppliedInvoicesForCredit($company_id, $credit_id);
		// $applied = array_unique(array_merge($already_applied, $applied_ids));
		return $this->credit_repository->searchInvoices($company_id, $currency_id, $client_id, $credit_id, $applied_ids, $paid_ids, $searched);
	}

	/**
	 * modifyCreditForApplying function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @param array $applied
	 * @return boolean
	 */
	private function modifyCreditForApplying(int $company_id, int $credit_id, array $applied) : bool {

		$applied_amount_sum = BigDecimal::of(0);

		foreach($applied as $ele){

			$ele['amount'] = Sanitize::input($ele['amount']);
			$applied_amount = BigDecimal::of($ele['amount']);
			$applied_amount_sum = $applied_amount_sum->plus($applied_amount);

		}

		$credit = $this->credit_repository->resetCredit($company_id, $credit_id);

		$credit_total_amount = BigDecimal::of($credit->amount);

		$new_left_to_apply_amount = $credit_total_amount->minus($applied_amount_sum);

		$status = CreditStatus::NOT_APPLIED->value;

		if($applied_amount_sum->isLessThan($credit_total_amount) && !$applied_amount_sum->isEqualTo(BigDecimal::of(0))){
			$status = CreditStatus::PARTIALLY_APPLIED->value;
		}else if($applied_amount_sum->isEqualTo($credit_total_amount)){
			$status = CreditStatus::APPLIED->value;
		}

		return $this->credit_repository->updateCreditForApplying($credit, $status, $applied_amount_sum->toScale(2, RoundingMode::HalfUp)->__toString(), $new_left_to_apply_amount->toScale(2, RoundingMode::HalfUp)->__toString());

	}

	/**
	 * getInvoiceIds function
	 *
	 * @param array $applied
	 * @return array
	 */
	private function getInvoiceIds(array $applied) : array {

		$ids = [];

		foreach($applied as $ele){
			$ele['id'] = Sanitize::input($ele['id']);
			if(!in_array($ele['id'], $ids)){
				array_push($ids, $ele['id']);
			}
		}

		return $ids;

	}

	/**
	 * modifyLedger function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @param array $applied
	 * @return void
	 */
	private function modifyLedger(int $company_id, int $credit_id, array $applied) : void {

		$this->credit_repository->forceRemoveLedgreEntriesForCredit($company_id, $credit_id);

		$data = [];

		foreach($applied as $ele){

			$ele['id'] = Sanitize::input($ele['id']);
			$ele['amount'] = Sanitize::input($ele['amount']);

			$data[] = [
				'invoice_id'		=>	$ele['id'],
				'applied_amount'	=>	$ele['amount']
			];
		}

		$this->credit_repository->insertLedgerEntries($company_id, $credit_id, $data);

	}

	/**
	 * modifyInvoices function
	 *
	 * @param integer $company_id
	 * @param array $applied
	 * @param boolean $removal_provided
	 * @return array
	 */
	private function modifyInvoices(int $company_id, array $applied, bool $removal_provided = false) : array {

		if(!$removal_provided){
			$invoice_ids = $this->getInvoiceIds($applied);
		}else{
			$invoice_ids = $applied;
		}
		
		$invoices = $this->credit_repository->fetchInvoicesForCreditApplying($company_id, $invoice_ids);
		$ledger_entries = $this->credit_repository->fetchLedgerForCreditApplying($company_id, $invoice_ids);

		$update = [];

		foreach($applied as $ele){
			
			$temp = [];

			$temp['id'] = $removal_provided ? (int) Sanitize::input($ele) : (int) Sanitize::input($ele['id']);
			
			$applied_sum = BigDecimal::of(0);

			foreach($ledger_entries as $entry){
				if((int) $entry['invoice_id'] === (int) $temp['id']){
					$applied_row_amount = BigDecimal::of($entry['total_applied']);
					$applied_sum = $applied_sum->plus($applied_row_amount);
				}
			}

			
			foreach($invoices as $invoice){

				if((int) $invoice['id'] === (int) $temp['id']){

					$status = InvoiceStatus::DRAFT->value;

					if($invoice['sent_at'] !== null || (int) $invoice['reminders_sent'] > 0){
						$status = InvoiceStatus::SENT->value;
					}

					$total = BigDecimal::of($invoice['total']);

					if($applied_sum->isLessThan($total) && !$applied_sum->isEqualTo(BigDecimal::of(0))){
						$status = InvoiceStatus::PARTIALLY_PAID->value;
					}else if($applied_sum->isEqualTo($total)){
						$status = InvoiceStatus::PAID->value;
					}

					$balance_due = $total->minus($applied_sum);

					$temp['status'] = $status;
					$temp['balance_due'] = $balance_due->toScale(2, RoundingMode::HalfUp)->__toString();

					break;

				}

			}

			$update[] = $temp;

		}
		
		$this->credit_repository->updateInvoicesForCreditApplying($update);

		return $invoice_ids;

	}


	/**
	 * applyCreditAmountToInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @param array $applied
	 * @return void
	 */
	public function applyCreditAmountToInvoices(int $company_id, int $credit_id, array $applied, array $removed_ids) : void {

		DB::transaction(function() use ($company_id, $credit_id, $applied, $removed_ids) {

			$this->modifyCreditForApplying($company_id, $credit_id, $applied); //reset credit and apply.
			$this->modifyLedger($company_id, $credit_id, $applied); //remove ledger entries and add new ones.
			$this->modifyInvoices($company_id, $removed_ids, true);
			$this->modifyInvoices($company_id, $applied, false);

			DB::afterCommit(function() use ($company_id, $applied, $removed_ids) {
				$ids = $this->getInvoiceIds($applied);
				$all_ids = array_unique(array_merge($ids, $removed_ids));
				foreach($all_ids as $applied_invoice_id){
					$invoice_id = (int) Sanitize::input($applied_invoice_id);
					GenerateInvoiceSnapshotJob::dispatch($company_id, $invoice_id, true, false);					
				}
			});
			
		});

	}

	/**
	 * fetchAlreadyAppliedInvoicesForCredit function
	 *
	 * @param integer $company_id
	 * @param integer $credit_id
	 * @return array
	 */
	public function fetchAlreadyAppliedInvoicesForCredit(int $company_id, int $credit_id) : array {
		return $this->credit_repository->fetchAlreadyAppliedInvoicesForCredit($company_id, $credit_id);
	}

	/**
	 * ifCreditNumberExists function
	 *
	 * @param string $credit_number
	 * @param integer|null $ignore_id
	 * @return boolean
	 */
	public function ifCreditNumberExists(string $credit_number, int $ignore_id = null) : bool {

		if($this->credit_repository->ifCreditNumberExists($credit_number, $ignore_id)){
			throw new CreditException('Credit number already exists', 'already_exists_cn', (int) config('global.error_code'));
		}

		return false;

	}

}