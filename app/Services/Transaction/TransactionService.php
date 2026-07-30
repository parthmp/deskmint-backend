<?php

namespace App\Services\Transaction;

use App\Exceptions\TransactionException;
use App\Models\Transaction;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Enums\TransactionStatus;
use App\Modules\Payment\Traits\UpdateInvoiceForTransaction;
use App\Repositories\Transaction\TransactionRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
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
						'table' => 'currencies',
						'first' => 'currencies.id',
						'operator' => '=',
						'second' => 'transactions.currency_id',
						'columns' => ['currencies.code as c_code']
					],
				];

			$default_columns = [
				'searchable_columns'	=>	['transactions.amount', 'currencies.code', 'transactions.payment_method', 'transactions.status', 'transactions.payment_gateway'],
				'searchable_dates'		=>	['transactions.created_at', 'transactions.paid_at'],
				'show_columns'			=>	[
					
					[
						'label'	=>	'amount',
						'text'	=>	'Amount',
					],
					[
						'label'	=>	'c_code',
						'text'	=>	'Currency',
					],
					[
						'label'	=>	'payment_gateway',
						'text'	=>	'Payment gateway',
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

			$gateways = PaymentGateway::configuredOptions(false);

			$rewrites = [
				'data' => [
					'transactions.status' => [
						TransactionStatus::PENDING->value				=>	TransactionStatus::PENDING->label(),
						TransactionStatus::REFUNDED->value				=>	TransactionStatus::REFUNDED->label(),
						TransactionStatus::COMPLETED->value				=>	TransactionStatus::COMPLETED->label(),
						TransactionStatus::VOID->value					=>	TransactionStatus::VOID->label(),
						TransactionStatus::PARTIALLY_REFUNDED->value	=>	TransactionStatus::PARTIALLY_REFUNDED->label(),
					],
					'transactions.payment_gateway' => $gateways,
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
					'payment_gateway' =>	[
						
					]
				]

			];

			foreach($gateways as $gateway_key => $gateway){
				$rewrites['ui']['payment_gateway'][] = [
					'type'		=>	'label',
					'highlight'	=>	'info',
					'text'		=>	$gateway,
					'value'		=>	$gateway_key
				];
			}

		return $this->easy_index->setType('transaction')->setJoins($joins)->setExceptionClass(TransactionException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
			'c_code'						=>		'currencies.code'
		 ])->setRewrites($rewrites)->setModel(Transaction::class)->fetchIndex();
	}

	
	/**
	 * updateInvoiceForTransaction function
	 *
	 * @param Transaction $transaction
	 * @param float $amount
	 * @return void
	 */
	public function updateInvoiceForTransaction(Transaction $transaction, float $amount){
		$this->updateInvoiceStatusForPayments($transaction, false);
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
	 * fetchTransaction function
	 *
	 * @param integer $transaction_id
	 * @return array
	 */
	public function fetchTransactionView(int $transaction_id, int $company_id) : array {
		return $this->transaction_repository->fetchTransactionView($transaction_id, $company_id);
	}

	/**
	 * fetchTransaction function
	 *
	 * @param integer $company_id
	 * @param integer $transaction_id
	 * @return Transaction
	 */
	public function fetchTransaction(int $company_id, int $transaction_id) : Transaction {
		return $this->transaction_repository->fetchTransaction($company_id, $transaction_id);
	}

}