<?php

namespace App\Services\PaymentRequest;

use App\Enums\PaymentRequests\PaymentRequestStatus;
use App\Exceptions\PaymentRequestException;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Jobs\SendGenericEmailJob;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\InvoiceGeneration\Traits\Generic;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Repositories\Client\ClientRepository;
use App\Repositories\PaymentRequest\PaymentRequestRepository;
use App\Repositories\PaymentType\PaymentTypeRepository;
use App\Services\EmailSettingsContent\EmailSettingsContentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * PaymentRequestService class
 */
class PaymentRequestService {

	use Generic;

	public function __construct(
		private PaymentRequestRepository $payment_request_repository,
		private ClientRepository $client_repository,
		private EmailSettingsContentService $email_settings_content_service,
		private PaymentTypeRepository $payment_type_repository,
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
	 * parseEmailContent function
	 *
	 * @param string $content
	 * @param array $data
	 * @param string $url_type
	 * @return string
	 */
	public function parseEmailContent(string $content, array $data, string $url_type) : string {
		
		$currency = $data['currency'];
		$client_first_name = $data['first_name'];
		$client_last_name = $data['last_name'];
		
		$payment_gateway_url = '';
		
		if((int) $data['payment_gateway'] !== PaymentGateway::NONE->value){
			$payment_gateway_url = URL::signedRoute($url_type, ['uuid' => $data['uuid']]);
		}
		

		$search = [
			'{$client_first_name}',
			'{$client_last_name}',
			'{$payment_url}'
		];

		$replace = [
			$client_first_name,
			$client_last_name,
			$payment_gateway_url
		];

		if((int) $data['payment_gateway'] === PaymentGateway::NONE->value){
			$content = $this->replaceBetweenTags($content,  '[{online-payment-start}]', '[{online-payment-end}]', '');
		}

		$content = str_ireplace($search, $replace, $content);
		$content = str_ireplace('{$unpaid_balance}', $data['amount'].' '.$currency, $content);
		$content = str_ireplace('[{online-payment-start}]', '', $content);
		$content = str_ireplace('[{online-payment-end}]', '', $content);
		
		return $content;

	}

	/**
	 * sendRequest function
	 *
	 * @param integer $company_id
	 * @param int $payment_request_id
	 * @return void
	 */
	public function sendRequest(int $company_id, int $payment_request_id) : void {

		$email_settings = $this->email_settings_content_service->fetchRecord($company_id);
		if(!$email_settings){
			throw new PaymentRequestException('invalid email content', 'invalid_email_content', (int) config('global.error_code'));
		}

		$content = json_decode($email_settings->settings_json, true);
		$content = $content['email_content_payment_request'];

		$data = $this->payment_request_repository->fetchDataForSendingRequest($company_id, $payment_request_id);
		$content = $this->parseEmailContent($content, $data, 'payment_request.pay');

		SendGenericEmailJob::dispatch([
			'subject'		=>	'You have received a payment request for '.$data['amount'],
			'email'			=>	$data['email'],
			'first_name'	=>	$data['first_name'],
			'last_name'		=>	$data['last_name'],
			'content'		=>	$content,
		]);

	}

	/**
	 * ifCurrencyAllowed function
	 *
	 * @param integer $payment_gateway
	 * @param string $currency_code
	 * @return boolean
	 */
	public function ifCurrencyAllowed(int $payment_gateway, string $currency_code) : bool {

		if((int) $payment_gateway === PaymentGateway::NONE->value){
			return true;
		}

		if((int) $payment_gateway === PaymentGateway::PAYPAL->value){
			return in_array($currency_code, config('payment.supported_currencies.paypal'));
		}else if((int) $payment_gateway === PaymentGateway::STRIPE->value){
			return in_array($currency_code, config('payment.supported_currencies.stripe'));
		}

		return false;

	}

	/**
	 * create function
	 *
	 * @param array $data
	 * @return PaymentRequest
	 */
	public function create(array $data) : PaymentRequest {

		$currency = $this->client_repository->fetchClientCurrencyById((int) $data['client_id']);

		if(!$this->ifCurrencyAllowed((int) $data['payment_gateway'], $currency->code)){
			throw new PaymentRequestException('Currency '.$currency->code.' is not supported by '.PaymentGateway::getLabelByValue((int) $data['payment_gateway']), 'currency_not_supported', (int) config('global.error_code'));
		}

		$pass_data = [];

		$pass_data['company_id'] = (int) $data['company_id'];
		$pass_data['client_id'] = (int) $data['client_id'];
		$pass_data['currency_id'] = (int) $currency->id;
		$pass_data['transaction_id'] = null;
		$pass_data['label'] = (string) $data['label'];
		$pass_data['amount'] = (string) $data['amount'];
		$pass_data['status'] = ((bool) $data['send_request']) ? PaymentRequestStatus::SENT->value : PaymentRequestStatus::DRAFT->value;
		$pass_data['payment_gateway'] = (int) $data['payment_gateway'];
		$pass_data['reminders_sent'] = 0;
		$pass_data['sent_at'] = ((bool) $data['send_request']) ? now() : null;

		return $this->payment_request_repository->createOrUpdate($pass_data);
	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @param integer $id
	 * @return PaymentRequest
	 */
	public function update(array $data, int $company_id, int $id) : PaymentRequest {

		$currency = $this->client_repository->fetchClientCurrencyById((int) $data['client_id']);

		if(!$this->ifCurrencyAllowed((int) $data['payment_gateway'], $currency->code)){
			throw new PaymentRequestException('Currency '.$currency->code.' is not supported by '.PaymentGateway::getLabelByValue((int) $data['payment_gateway']), 'currency_not_supported', (int) config('global.error_code'));
		}

		$payment_request = $this->payment_request_repository->fetchByIdWithCompanyId($company_id, $id);

		if((int) $payment_request->status === PaymentRequestStatus::CANCELLED->value || (int) $payment_request->status === PaymentRequestStatus::COMPLETED->value){
			throw new PaymentRequestException('You are not allowed to modify cancelled and completed requests', 'not_allowed_cancelled_and_completed', (int) config('global.error_code'));
		}

		$pass_data = [];

		$pass_data['client_id'] = (int) $data['client_id'];
		$pass_data['currency_id'] = (int) $currency->id;
		$pass_data['transaction_id'] = $payment_request->transaction_id;
		$pass_data['label'] = (string) $data['label'];
		$pass_data['amount'] = (string) $data['amount'];
		$pass_data['status'] = ((bool) $data['send_request']) ? PaymentRequestStatus::SENT->value : $payment_request->status;
		$pass_data['payment_gateway'] = (int) $data['payment_gateway'];
		$pass_data['sent_at'] = ((bool) $data['send_request']) ? now() : $payment_request->sent_at;
		$pass_data['hidden_sent_at'] = ((bool) $data['send_request']) ? now() : $payment_request->hidden_sent_at;

		return $this->payment_request_repository->createOrUpdate($pass_data, $id);


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
			'c_code'				=>		'currencies.code',
			'full_name'				=>		'clients.full_name'
		 ])->setRewrites($rewrites)->setModel(PaymentRequest::class)->fetchIndex();
	}

	/**
	 * markSent function
	 *
	 * @param integer $company_id
	 * @param integer $payment_request_id
	 * @return boolean
	 */
	public function markSent(int $company_id, int $payment_request_id) : bool {

		$payment_request = $this->payment_request_repository->fetchByIdWithCompanyId($company_id, $payment_request_id);

		if((int) $payment_request->status === PaymentRequestStatus::CANCELLED->value || (int) $payment_request->status === PaymentRequestStatus::COMPLETED->value){
			throw new PaymentRequestException('You can not send this request', 'not_allowed_to_send', (int) config('global.error_code'));
		}

		return $this->payment_request_repository->markSent($payment_request);

	}

	/**
	 * markCancel function
	 *
	 * @param integer $company_id
	 * @param integer $payment_request_id
	 * @return boolean
	 */
	public function markCancel(int $company_id, int $payment_request_id) : bool {

		$payment_request = $this->payment_request_repository->fetchByIdWithCompanyId($company_id, $payment_request_id);

		if((int) $payment_request->status === PaymentRequestStatus::CANCELLED->value || (int) $payment_request->status === PaymentRequestStatus::COMPLETED->value){
			throw new PaymentRequestException('You can not cancel this request', 'not_allowed_to_cancel', (int) config('global.error_code'));
		}

		return $this->payment_request_repository->markCancel($payment_request);

	}

	/**
	 * fetchPaymentRequest function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return array
	 */
	public function fetchPaymentRequest(int $company_id, int $id) : array {

		$data = $this->payment_request_repository->fetchForEdit($company_id, $id);

		return [
			'full_name'					=> $data['full_name'],
			'client_id'					=> $data['client_id'],
			'client_currency'			=> $data['client_currency'],
			'payment_request_currency'	=> $data['payment_request_currency'],
			'label'						=> $data['label'],
			'amount'					=> $data['amount'],
			'payment_gateway'			=> $data['payment_gateway'],
			'payment_gateways' 			=> $this->payment_request_repository->fetchInit($company_id)
		];
	}

	/**
	 * markCompleted function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return boolean
	 */
	public function markCompleted(int $company_id, int $id) : bool {
		
		$pr = $this->payment_request_repository->fetchByIdWithCompanyId($company_id, $id);
		
		if((int) $pr->status === PaymentRequestStatus::CANCELLED->value || (int) $pr->status === PaymentRequestStatus::COMPLETED->value){
			throw new PaymentRequestException('You can not mark this request as completed', 'not_allowed_to_completed', (int) config('global.error_code'));
		}

		return $this->payment_request_repository->markCompleted($pr);

	}

	/**
	 * fetchPaymentTypes function
	 *
	 * @param boolean $for_select
	 * @return array
	 */
	public function fetchPaymentTypes($for_select = false) : array {
		
		$fields = $this->payment_type_repository->fetchAllPaymentTypes();

		if(!$for_select){
			return $fields->toArray();
		}

		return $fields->map(function($item){
			return [
				'value' => $item->id,
        		'text'  => $item->payment_type
			];
		})->toArray();

	}

	/**
	 * createPaymentForRequest function
	 *
	 * @param integer $company_id
	 * @param integer $pr_id
	 * @param integer $payment_type_id
	 * @return Payment
	 */
	public function createPaymentForRequest(int $company_id, int $pr_id, int $payment_type_id) : Payment {

		$payment_type = $this->payment_type_repository->fetchById((int) $payment_type_id);
		if(!$payment_type){
			throw new PaymentRequestException('Invalid payment type provided', 'invalid_payment_type', (int) config('global.error_code'));
		}

		$pr = $this->payment_request_repository->fetchByIdWithCompanyId($company_id, $pr_id);

		$data = [
			'company_id'		=>	(int) $company_id,
			'client_id'			=>	(int) $pr->client_id,
			'payment_type_id'	=>	(int) $payment_type_id,
			'amount'			=>	(string) $pr->amount,
			'currency_id'		=>	(int) $pr->currency_id
		];

		return $this->payment_request_repository->createPaymentForRequest($data);

	}

}