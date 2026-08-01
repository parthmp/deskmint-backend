<?php

namespace App\Services\Credit;

use App\Enums\Credits\CreditStatus;
use App\Exceptions\CreditException;
use App\Models\Credit;
use App\Modules\EasyIndex\EasyIndex;
use App\Repositories\Credit\CreditRepository;
use Exception;
use Illuminate\Http\Request;

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

}