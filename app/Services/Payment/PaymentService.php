<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Helpers\Sanitize;
use App\Models\Payment;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Repositories\Credit\CreditRepository;
use App\Repositories\Payment\PaymentRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PaymentService class
 */
class PaymentService {

	public function __construct(
		private EasyIndex $easy_index,
		private CreditRepository $credit_repository,
		private PaymentRepository $payment_repository
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
						'columns' => ['transactions.gateway_fees_amount as gateway_fees_amount', 'transactions.received_amount as received_amount', 'transactions.payment_gateway as payment_gateway', 'transactions.token_id_identifier as token_id_identifier']
					],
					[
						'table' => 'payment_types',
						'first' => 'payment_types.id',
						'operator' => '=',
						'second' => 'payments.payment_type_id',
						'columns' => ['payment_types.payment_type as payment_type_n']
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
				'searchable_columns'	=>	['clients.full_name', 'currencies.code', 'payments.status', 'payments.amount', 'payments.applied_amount', 'payments.amount_left_to_be_applied', 'transactions.gateway_fees_amount', 'transactions.received_amount', 'transactions.payment_gateway', 'transactions.token_id_identifier', 'payments.payment_type'],
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
			
			$company_id = (int) Sanitize::input($request->input('company_id'));
			$gateways = PaymentGateway::configuredOptions($company_id, false);

			$rewrites = [
				'data' => [
					
					'payments.status' => [
						PaymentStatus::NOT_APPLIED->value			=>	PaymentStatus::NOT_APPLIED->label(),
						PaymentStatus::PARTIALLY_APPLIED->value		=>	PaymentStatus::PARTIALLY_APPLIED->label(),
						PaymentStatus::APPLIED->value				=>	PaymentStatus::APPLIED->label()
					],
					'transactions.payment_gateway' => $gateways,
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


		return $this->easy_index->setType('payment')->setJoins($joins)->setExceptionClass(PaymentException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
			'c_code'						=>		'currencies.code',
			'payment_type_n'				=>		'payment_types.payment_type',
			'gateway_fees_amount'			=>		'transactions.gateway_fees_amount',
			'received_amount'				=>		'transactions.received_amount',
			'payment_gateway'				=>		'transactions.payment_gateway',
			'token_id_identifier'			=>		'transactions.token_id_identifier',
			'full_name'						=>		'clients.full_name'
		 ])->setRewrites($rewrites)->setModel(Payment::class)->fetchIndex();

	}

	/**
	 * create function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param string $amount
	 * @param integer $payment_type_id
	 * @return Payment
	 */
	public function create(int $company_id, int $client_id, string $amount, int $payment_type_id) : Payment {
		$currency_id = $this->credit_repository->fetchClientCurrencyId($company_id, $client_id);
		return $this->payment_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, '0.00', $amount, PaymentStatus::NOT_APPLIED->value, $payment_type_id);
	}

	/**
	 * fetchAppliedPaymentInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @return array
	 */
	public function fetchAppliedPaymentInvoices(int $company_id, int $payment_id) : array {
		return $this->payment_repository->fetchAppliedPaymentInvoices($company_id, $payment_id);
	}

	/**
	 * fetchPaymentForEdit function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return array
	 */
	public function fetchPaymentForEdit(int $company_id, int $id) : array {
		return $this->payment_repository->fetchPaymentForEdit($company_id, $id);
	}

	/**
	 * update function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param string $amount
	 * @param integer $payment_id
	 * @param integer $payment_type_id
	 * @return Payment
	 */
	public function update(int $company_id, int $client_id, string $amount, int $payment_id, int $payment_type_id) : Payment {

		return DB::transaction(function () use ($company_id, $client_id, $amount, $payment_id, $payment_type_id) {

			//check access first.
			$payment = $this->payment_repository->fetchById($company_id, $payment_id);

			if(!$payment){
				throw new PaymentException('Invalid payment provided', 'invalid_payment', (int) config('global.error_code'));
			}

			if($payment->transaction_id !== null){
				throw new PaymentException('You can not update payment gateway received payments', 'unable_to_update_rec_payments', (int) config('global.error_code'));
			}

			$currency_id = $this->credit_repository->fetchClientCurrencyId($company_id, $client_id);

			if((int) $payment->status === PaymentStatus::NOT_APPLIED->value){
				//update it fully.
				return $this->payment_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, '0.00', $amount, PaymentStatus::NOT_APPLIED->value, $payment_type_id, null, $payment_id);
			}

			//else check for further.
			//client should not be changed.
			//currency should not be changed.
			//amount must not be lower than applied amount.
			//update.

			if((int) $client_id !== (int) $payment->client_id){
				throw new PaymentException('You can not change client for this entry', 'unable_to_change_client', (int) config('global.error_code'));
			}

			if((int) $currency_id !== (int) $payment->currency_id){
				throw new PaymentException('You can not change currency for this entry', 'unable_to_change_currency', (int) config('global.error_code'));
			}

			$applied_amount = BigDecimal::of($payment->applied_amount);
			$amount = BigDecimal::of($amount);

			if($amount->isLessThan($applied_amount)){
				throw new PaymentException('You can not update it for amount less than '.$payment->applied_amount, 'unable_to_update_invalid_amount', (int) config('global.error_code'));
			}

			$left_to_apply = $amount->minus($applied_amount);

			$status = PaymentStatus::PARTIALLY_APPLIED->value;

			if($left_to_apply->isEqualTo(BigDecimal::of(0))){
				$status = PaymentStatus::APPLIED->value;
			}

			$left_to_apply = $left_to_apply->toScale(2, RoundingMode::HalfUp)->__toString();
			return $this->payment_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, $payment->applied_amount, $left_to_apply, $status, $payment_type_id, null, $payment_id);

		});

	}

}