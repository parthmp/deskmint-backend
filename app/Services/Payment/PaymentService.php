<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\Payment\Enums\PaymentStatus;
use Illuminate\Http\Request;

/**
 * PaymentService class
 */
class PaymentService {

	public function __construct(
		private EasyIndex $easy_index
	){}

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
						'second' => 'payments.client_id',
						'columns' => ['clients.full_name as full_name']
					],
					[
						'table' => 'transactions',
						'first' => 'transactions.id',
						'operator' => '=',
						'second' => 'payments.transaction_id',
						'columns' => ['transactions.gateway_fees_amount as gateway_fees_amount', 'transactions.received_amount as received_amount', 'transactions.payment_gateway as payment_gateway', 'transactions.token_id_identifier as token']
					],
					[
						'table' => 'payment_types',
						'first' => 'payment_types.id',
						'operator' => '=',
						'second' => 'payments.payment_type_id',
						'columns' => ['currencies.code as c_code']
					],
					[
						'table' => 'currencies',
						'first' => 'currencies.id',
						'operator' => '=',
						'second' => 'payments.currency_id',
						'columns' => ['currencies.code as c_code']
					],
				];

			$default_columns = [
				'searchable_columns'	=>	['clients.full_name', 'currencies.code', 'payments.status', 'payments.amount', 'payments.applied_amount', 'payments.amount_left_to_be_applied'],
				'searchable_dates'		=>	['payments.created_at'],
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
					
					'payments.status' => [
						PaymentStatus::NOT_APPLIED->value			=>	PaymentStatus::NOT_APPLIED->label(),
						PaymentStatus::PARTIALLY_APPLIED->value		=>	PaymentStatus::PARTIALLY_APPLIED->label(),
						PaymentStatus::APPLIED->value				=>	PaymentStatus::APPLIED->label()
					]
				],
				'ui'	=>	[
					'status'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	PaymentStatus::NOT_APPLIED->label(),
							'value'		=>	PaymentStatus::NOT_APPLIED->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	PaymentStatus::PARTIALLY_APPLIED->label(),
							'value'		=>	PaymentStatus::PARTIALLY_APPLIED->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	PaymentStatus::APPLIED->label(),
							'value'		=>	PaymentStatus::APPLIED->value
						]
					]
				]

			];

		return $this->easy_index->setType('payment')->setJoins($joins)->setExceptionClass(PaymentException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
			'c_code'						=>		'currencies.code',
			'full_name'						=>		'clients.full_name'
		 ])->setRewrites($rewrites)->setModel(Payment::class)->fetchIndex();

	}

}