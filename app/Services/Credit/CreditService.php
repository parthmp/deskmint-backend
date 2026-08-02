<?php

namespace App\Services\Credit;

use App\Enums\Credits\CreditStatus;
use App\Exceptions\CreditException;
use App\Models\Credit;
use App\Modules\EasyIndex\EasyIndex;
use App\Repositories\Credit\CreditRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Illuminate\Http\Request;
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
	 * @return Credit
	 */
	public function create(int $company_id, int $client_id, string $amount) : Credit {

		$currency_id = $this->credit_repository->fetchClientCurrencyId($company_id, $client_id);
		return $this->credit_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, '0.00', $amount, CreditStatus::NOT_APPLIED->value);

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
	 * @param integer $credit_id
	 * @return Credit
	 */
	public function update(int $company_id, int $client_id, string $amount, int $credit_id) : Credit {

		return DB::transaction(function () use ($company_id, $client_id, $amount, $credit_id) {

			//check access first.
			$credit = $this->credit_repository->fetchById($company_id, $credit_id);

			if(!$credit){
				throw new CreditException('Invalid credit provided', 'invalid_credit', (int) config('global.error_code'));
			}

			$currency_id = $this->credit_repository->fetchClientCurrencyId($company_id, $client_id);

			if((int) $credit->status === CreditStatus::NOT_APPLIED->value){
				//update it fully.
				return $this->credit_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, '0.00', $amount, CreditStatus::NOT_APPLIED->value, $credit_id);
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
			return $this->credit_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, $credit->applied_amount, $left_to_apply, $status, $credit_id);

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

}