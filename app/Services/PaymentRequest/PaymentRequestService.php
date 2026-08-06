<?php

namespace App\Services\PaymentRequest;

use App\Enums\PaymentRequests\PaymentRequestStatus;
use App\Exceptions\PaymentRequestException;
use App\Helpers\Sanitize;
use App\Models\PaymentRequest;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Repositories\Client\ClientRepository;
use App\Repositories\PaymentRequest\PaymentRequestRepository;
use Illuminate\Http\Request;

/**
 * PaymentRequestService class
 */
class PaymentRequestService {

	public function __construct(
		private PaymentRequestRepository $payment_request_repository,
		private ClientRepository $client_repository,
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

		$currency = $this->client_repository->fetchClientCurrencyById((int) $data['client_id']);

		$pass_data = [];

		$pass_data['company_id'] = (int) $data['company_id'];
		$pass_data['client_id'] = (int) $data['client_id'];
		$pass_data['currency_id'] = (int) $currency->id;
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
						'second' => 'payment_requests.currency_id',
						'columns' => ['currencies.code as c_code']
					],
					[
						'table' => 'clients',
						'first' => 'clients.id',
						'operator' => '=',
						'second' => 'payment_requests.client_id',
						'columns' => ['clients.full_name as full_name']
					],
					[
						'table' => 'transactions',
						'first' => 'transactions.id',
						'operator' => '=',
						'second' => 'payment_requests.transaction_id',
						'columns' => ['transactions.token_id_identifier as token']
					],
					
				];

			$default_columns = [
				'searchable_columns'	=>	['clients.full_name', 'currencies.code', 'transactions.token_id_identifier', 'payment_requests.label', 'payment_requests.status', 'payment_requests.payment_gateway'],
				'searchable_dates'		=>	['payment_requests.created_at'],
				'show_columns'			=>	[
					
					[
						'label'	=>	'full_name',
						'text'	=>	'Client',
					],
					[
						'label'	=>	'c_code',
						'text'	=>	'Currency',
					],
					[
						'label'	=>	'token_id_identifier',
						'text'	=>	'Token',
					],
					[
						'label'	=>	'label',
	 					'text'	=>	'Label',
					],
					[
						'label'	=>	'Status',
	 					'text'	=>	'status',
					],
					[
						'label'	=>	'payment_gateway',
	 					'text'	=>	'Gateway',
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
					'payment_requests.status' => [
						PaymentRequestStatus::DRAFT->value				=>	PaymentRequestStatus::DRAFT->label(),
						PaymentRequestStatus::SENT->value				=>	PaymentRequestStatus::SENT->label(),
						PaymentRequestStatus::CANCELLED->value			=>	PaymentRequestStatus::CANCELLED->label(),
						PaymentRequestStatus::COMPLETED->value			=>	PaymentRequestStatus::COMPLETED->label(),
					],
					'payment_requests.payment_gateway' => $gateways,
					'payment_requests.send_reminders' => [
						0			=>	'No',
						1			=>	'Yes'
					]
				],
				'ui'	=>	[
					'send_reminders' => [
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
							'highlight'	=>	'info',
							'text'		=>	PaymentRequestStatus::DRAFT->label(),
							'value'		=>	PaymentRequestStatus::DRAFT->value,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	PaymentRequestStatus::SENT->label(),
							'value'		=>	PaymentRequestStatus::SENT->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	PaymentRequestStatus::CANCELLED->label(),
							'value'		=>	PaymentRequestStatus::CANCELLED->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	PaymentRequestStatus::COMPLETED->label(),
							'value'		=>	PaymentRequestStatus::COMPLETED->value
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

		return $this->easy_index->setType('payment_request')->setJoins($joins)->setExceptionClass(PaymentRequestException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
			'c_code'						=>		'currencies.code',
			'full_name'				=>		'clients.full_name'
		 ])->setRewrites($rewrites)->setModel(PaymentRequest::class)->fetchIndex();
	}

}