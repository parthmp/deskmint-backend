<?php

namespace App\Services\PaymentRequest;

use App\Enums\PaymentRequests\PaymentRequestStatus;
use App\Modules\EasyIndex\EasyIndex;
use App\Repositories\PaymentRequest\PaymentRequestRepository;
use Illuminate\Http\Request;

/**
 * PaymentRequestService class
 */
class PaymentRequestService {

	public function __construct(
		private PaymentRequestRepository $payment_request_repository,
		private EasyIndex $easy_index
	){}

	/**
	 * fetchInit function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchInit(int $company_id) : array {
		return $this->payment_request_repository->fetchInit($company_id);
	}

	/**
	 * create function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function create(array $data) : bool {

		$pass_data = [];

		$pass_data['company_id'] = (int) $data['company_id'];
		$pass_data['client_id'] = (int) $data['client_id'];
		$pass_data['transaction_id'] = null;
		$pass_data['label'] = (string) $data['label'];
		$pass_data['amount'] = (string) $data['amount'];
		$pass_data['status'] = ((bool) $data['send_request']) ? PaymentRequestStatus::SENT->value : PaymentRequestStatus::DRAFT->value;
		$pass_data['payment_gateway'] = (int) $data['payment_gateway'];
		$pass_data['send_reminders'] = ((bool) $data['send_reminders']) ? 1 : 0;
		$pass_data['reminders_sent'] = 0;

		return $this->payment_request_repository->createOrUpdate($pass_data);
	}

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

			$company_id = (int) Sanitize::input($request->input('company_id'));

			$gateways = PaymentGateway::configuredOptions($company_id, false);

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

}