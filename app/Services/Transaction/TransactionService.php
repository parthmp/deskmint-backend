<?php

namespace App\Services\Transaction;

use App\Exceptions\TransactionException;
use App\Models\Product;
use App\Models\Transaction;
use App\Modules\DataTable\DataTable;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Repositories\Product\ProductRepository;
use Illuminate\Http\Request;

/**
 * TransactionService class
 */
class TransactionService {

	public function __construct(private EasyIndex $easy_index){}

	public function fetch(Request $request) : array {
		$joins = [
					[
						'table' => 'invoices',
						'first' => 'invoices.id',
						'operator' => '=',
						'second' => 'transactions.invoice_id',
						'columns' => '' //this will be replaced by EasyIndex class.
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
				'searchable_columns'	=>	['invoices.invoice_number', 'invoices.full_name', 'transactions.amount', 'currencies.code', 'transactions.payment_method', 'transactions.status', ],
				'searchable_dates'		=>	['transactions.created_at', 'invoices.due_date', 'invoices.invoice_date', 'invoices.last_reminder_sent_at'],
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
						'label'	=>	'Amount',
						'text'	=>	'amount',
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
					'invoices.discount_type' => [
						1	=>	'Percentage',
						2	=>	"Amount"
					],
					'invoices.status' => [
						InvoiceStatus::PENDING->value				=>	InvoiceStatus::PENDING->label(),
						InvoiceStatus::CANCELLED->value				=>	InvoiceStatus::CANCELLED->label(),
						InvoiceStatus::PARTIALLY_PAID->value		=>	InvoiceStatus::PARTIALLY_PAID->label(),
						InvoiceStatus::PAID->value					=>	InvoiceStatus::PAID->label(),
					],
					'transactions.payment_method' => [
						1	=>	'Cash',
						2	=>	'Netbanking',
						3	=>	'PayPal',
						4	=>	'Stripe',
					]
				],
				'ui'	=>	[
					'discount_type'	=>	[
						[
							'type'			=>	'label',
							'highlight'		=>	'success',
							'text'			=>	'Percentage',
							'value'			=>	1,
						],
						[
							'type'			=>	'label',
							'highlight'		=>	'success',
							'text'			=>	'Amount',
							'value'			=>	2,
						]
					],
					'status'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	InvoiceStatus::CANCELLED->label(),
							'value'		=>	InvoiceStatus::CANCELLED->value,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	InvoiceStatus::PENDING->label(),
							'value'		=>	InvoiceStatus::PENDING->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	InvoiceStatus::PARTIALLY_PAID->label(),
							'value'		=>	InvoiceStatus::PARTIALLY_PAID->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	InvoiceStatus::PAID->label(),
							'value'		=>	InvoiceStatus::PAID->value
						]
					],
					'payment_method'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Cash',
							'value'		=>	1,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Netbanking',
							'value'		=>	2
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'PayPal',
							'value'		=>	3
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Stripe',
							'value'		=>	4
						]
					]
				]

			];

		return $this->easy_index->setType('invoice')->setJoins($joins)->setExceptionClass(TransactionException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
			'c_code'				=>		'currencies.code'
		 ])->setRewrites($rewrites)->setModel(Transaction::class)->fetchIndex();
	}

}