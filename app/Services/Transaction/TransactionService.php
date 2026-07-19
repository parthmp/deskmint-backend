<?php

namespace App\Services\Transaction;

use App\Exceptions\TransactionException;
use App\Models\Product;
use App\Models\Transaction;
use App\Modules\DataTable\DataTable;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\TransactionStatus;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Transaction\TransactionRepository;
use Illuminate\Http\Request;

/**
 * TransactionService class
 */
class TransactionService {

	public function __construct(
		private EasyIndex $easy_index,
		private TransactionRepository $transaction_repository
	){}

	/**
	 * fetch function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetch(Request $request) : array {
		$joins = [
					[
						'table' => 'invoices',
						'first' => 'transactions.invoice_id',
						'operator' => '=',
						'second' => 'invoices.id',
						'columns' => ['invoices.full_name as full_name', 'invoices.invoice_number as invoice_number']
					],
					[
						'table' => 'currencies',
						'first' => 'currencies.id',
						'operator' => '=',
						'second' => 'invoices.currency_id',
						'columns' => ['currencies.code as c_code']
					],
				];

			$default_columns = [
				'searchable_columns'	=>	['invoices.invoice_number', 'invoices.full_name', 'transactions.amount', 'currencies.code', 'transactions.payment_method', 'transactions.status', 'invoices.invoice_number'],
				'searchable_dates'		=>	['transactions.created_at', 'transactions.paid_at'],
				'show_columns'			=>	[
					[
						'label'	=>	'invoice_number',
						'text'	=>	'Invoice#',
					],
					[
						'label'	=>	'full_name',
	 					'text'	=>	'Full name',
					],
					[
						'label'	=>	'amount',
						'text'	=>	'Amount',
					],
					[
						'label'	=>	'c_code',
						'text'	=>	'Currency',
					],
					[
						'label'	=>	'payment_method',
						'text'	=>	'Payment method',
					],
					[
						'label'	=>	'status',
	 					'text'	=>	'Status',
					],
					[
						'label'	=>	'created_at',
	 					'text'	=>	'Added on',
					]
				],
			];

			$rewrites = [
				'data' => [
					'transactions.status' => [
						TransactionStatus::PENDING->value				=>	TransactionStatus::PENDING->label(),
						TransactionStatus::REFUNDED->value				=>	TransactionStatus::REFUNDED->label(),
						TransactionStatus::COMPLETED->value				=>	TransactionStatus::COMPLETED->label(),
						TransactionStatus::VOID->value					=>	TransactionStatus::VOID->label(),
						TransactionStatus::PARTIALLY_REFUNDED->value	=>	TransactionStatus::PARTIALLY_REFUNDED->label(),
					],
					'transactions.payment_method' => [
						PAYMENT_CASH			=>	'Cash',
						PAYMENT_NETBANKING		=>	'Netbanking',
						PAYMENT_PAYPAL			=>	'PayPal',
						PAYMENT_STRIPE			=>	'Stripe',
					],
					'transactions.is_approved' => [
						0			=>	'No',
						1			=>	'Yes'
					],
					'transactions.is_payment_captured' => [
						0			=>	'No',
						1			=>	'Yes'
					],
					'transactions.is_echeck' => [
						0			=>	'No',
						1			=>	'Yes'
					]
				],
				'ui'	=>	[
					'is_approved' => [
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	'No',
							'value'		=>	0,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	'Yes',
							'value'		=>	1,
						]
					],
					'is_payment_captured' => [
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	'No',
							'value'		=>	0,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	'Yes',
							'value'		=>	1,
						]
					],
					'is_echeck' => [
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	'No',
							'value'		=>	0,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	'Yes',
							'value'		=>	1,
						]
					],
					'status'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	TransactionStatus::VOID->label(),
							'value'		=>	TransactionStatus::VOID->value,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	TransactionStatus::PENDING->label(),
							'value'		=>	TransactionStatus::PENDING->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	TransactionStatus::COMPLETED->label(),
							'value'		=>	TransactionStatus::COMPLETED->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	TransactionStatus::REFUNDED->label(),
							'value'		=>	TransactionStatus::REFUNDED->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	TransactionStatus::PARTIALLY_REFUNDED->label(),
							'value'		=>	TransactionStatus::PARTIALLY_REFUNDED->value
						]
					],
					'payment_method'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Cash',
							'value'		=>	PAYMENT_CASH,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Netbanking',
							'value'		=>	PAYMENT_NETBANKING
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'PayPal',
							'value'		=>	PAYMENT_PAYPAL
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Stripe',
							'value'		=>	PAYMENT_STRIPE
						]
					]
				]

			];

		return $this->easy_index->setType('transaction')->setJoins($joins)->setAndWhere([['transactions.status', '=', (int) TransactionStatus::COMPLETED->value]])->setExceptionClass(TransactionException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
			'c_code'						=>		'currencies.code',
			'full_name'						=>		'invoices.full_name',
			'invoice_number'				=>		'invoices.invoice_number',
		 ])->setRewrites($rewrites)->setModel(Transaction::class)->fetchIndex();
	}

	/**
	 * fetchInit function
	 *
	 * @return array
	 */
	public function fetchInit() : array {
		return [
			'offline_payment_methods' => [
				[
					'identifer'	=>	PAYMENT_CASH,
					'label'		=>	'Cash'
				],
				[
					'identifer'	=>	PAYMENT_NETBANKING,
					'label'		=>	'Netbanking'
				]
			]
		];
	}

	/**
	 * fetchInvoices function
	 *
	 * @param integer $company_id
	 * @param string $searched
	 * @return array
	 */
	public function fetchInvoices(int $company_id, string $searched) : array {
		
	}

}