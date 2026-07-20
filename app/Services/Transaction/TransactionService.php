<?php

namespace App\Services\Transaction;

use App\Exceptions\TransactionException;
use App\Models\Transaction;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\Payment\Enums\TransactionStatus;
use App\Modules\Payment\Traits\UpdateInvoiceForTransaction;
use App\Repositories\Transaction\TransactionRepository;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;

/**
 * TransactionService class
 */
class TransactionService {

	use UpdateInvoiceForTransaction;

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
					[
						'table' => 'users',
						'first' => 'users.id',
						'operator' => '=',
						'second' => 'transactions.voided_by',
						'columns' => ['users.name as u_user']
					],
				];

			$default_columns = [
				'searchable_columns'	=>	['invoices.invoice_number', 'invoices.full_name', 'transactions.amount', 'currencies.code', 'transactions.payment_method', 'transactions.status', 'invoices.invoice_number', 'users.name'],
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
			'u_user'						=>		'users.name',
		 ])->setRewrites($rewrites)->setModel(Transaction::class)->fetchIndex();
	}

	/**
	 * fetchInit function
	 *
	 * @return array
	 */
	public function fetchInit(?int $invoice_id = null) : array {
		
		$data = [
			'offline_payment_methods' => [
				[
					'value'		=>	PAYMENT_CASH,
					'text'		=>	'Cash'
				],
				[
					'value'		=>	PAYMENT_NETBANKING,
					'text'		=>	'Netbanking'
				]
			],
			'invoice_data' => null
		];

		if($invoice_id){
			$invoice_data = $this->transaction_repository->fetchInvoiceDataById($invoice_id);
			$data['invoice_data'] = $invoice_data;
		}

		return $data;
	}

	/**
	 * fetchInvoices function
	 *
	 * @param integer $company_id
	 * @param string $searched
	 * @return array
	 */
	public function fetchInvoices(int $company_id, string $searched) : array {
		return $this->transaction_repository->searchInvoicesByInvoiceNumber($company_id, $searched);
	}

	/**
	 * validateAmounts function
	 *
	 * @param string $amount
	 * @param string $gateway_fees
	 * @param string $received_amount
	 * @return boolean
	 */
	public function validateAmounts(string $amount, string $gateway_fees, string $received_amount) : bool {
		
		$amount = BigDecimal::of($amount);
		$gateway_fees = BigDecimal::of($gateway_fees);
		$received_amount = BigDecimal::of($received_amount);

		$combined = $gateway_fees->plus($received_amount);

		return $amount->isEqualTo($combined);

	}

	/**
	 * createManualTransaction function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param float $amount
	 * @param float $gateway_fees
	 * @param float $received_amount
	 * @param integer $payment_method
	 * @return Transaction
	 */
	public function createManualTransaction(int $company_id, int $invoice_id, float $amount, float $gateway_fees, float $received_amount, int $payment_method) : Transaction {
		return $this->transaction_repository->createManualTransaction($company_id, $invoice_id, $amount, $gateway_fees, $received_amount, $payment_method);
	}

	/**
	 * updateInvoiceForTransaction function
	 *
	 * @param Transaction $transaction
	 * @param float $amount
	 * @return void
	 */
	public function updateInvoiceForTransaction(Transaction $transaction, float $amount){
		$this->markInvoicePaidnDeduct($transaction, $amount, false);
	}

	/**
	 * generateSnapshot function
	 *
	 * @param integer $invoice_id
	 * @return void
	 */
	public function generateSnapshot(int $invoice_id){
		$this->updateInvoiceSnapshot($invoice_id);
	}

	/**
	 * validateInvoiceForTransaction function
	 *
	 * @param integer $invoice_id
	 * @return array
	 */
	public function validateInvoiceForTransaction(int $invoice_id) : array {
		return $this->transaction_repository->validateInvoiceForTransaction($invoice_id);
	}


	/**
	 * fetchTransaction function
	 *
	 * @param integer $transaction_id
	 * @return array
	 */
	public function fetchTransactionView(int $transaction_id, int $company_id) : array {
		return $this->transaction_repository->fetchTransactionView($transaction_id, $company_id);
	}

}