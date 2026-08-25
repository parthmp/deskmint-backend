<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Modules\EasyIndex\EasyIndex;
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
		return [];
		// $joins = [
		// 			[
		// 				'table' => 'clients',
		// 				'first' => 'clients.id',
		// 				'operator' => '=',
		// 				'second' => 'payments.client_id',
		// 				'columns' => ['clients.full_name as full_name']
		// 			],
		// 			[
		// 				'table' => 'transactions',
		// 				'first' => 'payments.transaction_id',
		// 				'operator' => '=',
		// 				'second' => 'transactions.id',
		// 				'columns' => ['transactions.gateway_fees_amount as gateway_fees_amount', 'transactions.received_amount as received_amount', 'transactions.payment_gateway as payment_gateway', 'transactions.token_id_identifier as token']
		// 			],
		// 			[
		// 				'table' => 'payment_types',
		// 				'first' => 'payment_types.id',
		// 				'operator' => '=',
		// 				'second' => 'payments.payment_type_id',
		// 				'columns' => ['currencies.code as c_code']
		// 			],
		// 			[
		// 				'table' => 'currencies',
		// 				'first' => 'currencies.id',
		// 				'operator' => '=',
		// 				'second' => 'transactions.currency_id',
		// 				'columns' => ['currencies.code as c_code']
		// 			],
		// 		];

		// 	$default_columns = [
		// 		'searchable_columns'	=>	['clients.full_name', 'currencies.code', 'credits.status', 'credits.amount', 'credits.applied_amount', 'credits.amount_left_to_be_applied'],
		// 		'searchable_dates'		=>	['clients.created_at'],
		// 		'show_columns'			=>	[
		// 			[
		// 				'label'	=>	'full_name',
		// 				'text'	=>	'Name',
		// 			],
		// 			[
		// 				'label'	=>	'c_code',
		// 				'text'	=>	'Currency',
		// 			],
		// 			[
		// 				'label'	=>	'status',
	 	// 				'text'	=>	'Status',
		// 			],
		// 			[
		// 				'label'	=>	'amount',
	 	// 				'text'	=>	'Amount',
		// 			],
		// 			[
		// 				'label'	=>	'applied_amount',
	 	// 				'text'	=>	'Applied',
		// 			],
		// 			[
		// 				'label'	=>	'amount_left_to_be_applied',
	 	// 				'text'	=>	'Amount left',
		// 			],
		// 			[
		// 				'label'	=>	'created_at',
	 	// 				'text'	=>	'Added on',
		// 			]
		// 		],
		// 	];

		// 	$rewrites = [
		// 		'data' => [
					
		// 			'credits.status' => [
		// 				CreditStatus::NOT_APPLIED->value			=>	CreditStatus::NOT_APPLIED->label(),
		// 				CreditStatus::PARTIALLY_APPLIED->value		=>	CreditStatus::PARTIALLY_APPLIED->label(),
		// 				CreditStatus::APPLIED->value				=>	CreditStatus::APPLIED->label()
		// 			]
		// 		],
		// 		'ui'	=>	[
		// 			'status'	=>	[
		// 				[
		// 					'type'		=>	'label',
		// 					'highlight'	=>	'error',
		// 					'text'		=>	CreditStatus::NOT_APPLIED->label(),
		// 					'value'		=>	CreditStatus::NOT_APPLIED->value
		// 				],
		// 				[
		// 					'type'		=>	'label',
		// 					'highlight'	=>	'info',
		// 					'text'		=>	CreditStatus::PARTIALLY_APPLIED->label(),
		// 					'value'		=>	CreditStatus::PARTIALLY_APPLIED->value
		// 				],
		// 				[
		// 					'type'		=>	'label',
		// 					'highlight'	=>	'success',
		// 					'text'		=>	CreditStatus::APPLIED->label(),
		// 					'value'		=>	CreditStatus::APPLIED->value
		// 				]
		// 			]
		// 		]

		// 	];

		// return $this->easy_index->setType('credit')->setJoins($joins)->setExceptionClass(PaymentException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
		// 	'c_code'						=>		'currencies.code',
		// 	'full_name'						=>		'clients.full_name'
		//  ])->setRewrites($rewrites)->setModel(Payment::class)->fetchIndex();

	}

}