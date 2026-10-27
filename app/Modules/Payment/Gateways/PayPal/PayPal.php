<?php

namespace App\Modules\Payment\Gateways\PayPal;

use App\Enums\RecurringInvoices\Frequencies;
use App\Exceptions\PaymentException;
use App\Models\Transaction;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Enums\TransactionStatus;
use App\Modules\Payment\Contracts\GenericDTO;
use App\Modules\Payment\Contracts\PaymentInterface;
use App\Modules\Payment\Enums\PayPalEvents;
use App\Modules\Payment\Gateways\PayPal\Implementation\Credentials;
use App\Modules\Payment\Gateways\PayPal\Implementation\Order;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Srmklive\PayPal\Services\PayPal as Provider;

class PayPal implements PaymentInterface {

	private ?array $order_response = null;
	private ?string $invoice_or_request_payment_url = null;
	private ?string $order_response_id = null;
	private ?GenericDTO $dto = null;
	private DatabaseOperations $db;
	private ?Provider $provider = null;
	private string $webhook_id;

	public function __construct(){
		$this->db = $this->getDB();
	}

	/**
	 * setCredentials function
	 *
	 * @param Credentials $credentials
	 * @return self
	 */
	public function setCredentials(Credentials $credentials) : self {
		$this->provider = $credentials->getProvider();
		$token = $this->provider->getAccessToken();
		$this->webhook_id = $credentials->getWebhookId();
		return $this;
	}

	/**
	 * setContext function
	 *
	 * @param GenericDTO $dto
	 * @return self
	 */
	public function setContext(GenericDTO $dto) : self {
		$this->dto = $dto;
		return $this;
	}

	/**
	 * createOrder function
	 *
	 * @param Order $order
	 * @return self
	 */
	public function createOrder(Order $order) : self {
		$this->order_response = $order->createOrder($this->provider);
		return $this;
	}

	/**
	 * getOrderResponse function
	 *
	 * @return array
	 */
	public function getOrderResponse() : array {
		return $this->order_response;
	}

	/**
	 * getDB function
	 *
	 * @return DatabaseOperations
	 */
	private function getDB() : DatabaseOperations {
		return app(DatabaseOperations::class);
	}

	/**
	 * createTransaction function
	 *
	 * @param string $order_id
	 * @param array $data
	 * @return Transaction
	 */
	private function createTransaction(string $order_id, array $data) : Transaction {
		return $this->db->insertTransaction([
			'currency_id'					=>	(int) $data['currency_id'],
			'company_id'					=>	(int) $data['company_id'],
			'amount'						=>	(string) $data['amount'],
			'payment_gateway'				=>	PaymentGateway::PAYPAL->value,
			'mode'							=>	(string) $data['mode'],
			'token_id_identifier'			=>	(string) $order_id,
			'is_approved'					=>	0,
			'is_payment_captured'			=>	0,
			'status'						=>	(int) TransactionStatus::PENDING->value
		]);
	}

	
	/**
	 * setInvoiceOrRequestData function
	 *
	 * @param boolean $is_payment_request
	 * @return self
	 */
	private function setInvoiceOrRequestData(bool $is_payment_request = false) : self {

		if(!$this->dto){
			throw new PaymentException("data is not set");
		}

		$data = $this->dto->getData();

		$invoice_id = null;
		$payment_request_id = null;

		if($is_payment_request){

			if(!$data['payment_request_id']){
				throw new PaymentException("payment request id not provided.");
			}

			$payment_request_id = (int) $data['payment_request_id'];

		}else{

			if(!$data['invoice_id']){
				throw new PaymentException("invoice id not provided.");
			}

			$invoice_id = (int) $data['invoice_id'];
		}

		$ref = $this->db->upsertTransactionReference((int) $data['company_id'], (int) $data['client_id'], $invoice_id, $payment_request_id, null, null);
		
		if(isset($this->order_response['id']) && $this->order_response['id'] != null){
			foreach($this->order_response['links'] as $link) {
				if($link['rel'] === 'approve'){

					$transaction = $this->createTransaction($this->order_response['id'], $data);
					$this->db->upsertTransactionReference((int) $data['company_id'], (int) $data['client_id'], $invoice_id, $payment_request_id, (int) $transaction->id, (int) $ref->id);

					$this->order_response_id = $this->order_response['id'];
					$this->invoice_or_request_payment_url = $link['href'];

					$this->db->insertEmptyTransactionGatewayDetails((int) $transaction->id);
					$this->db->insertPaymentUrl((int) $transaction->id, $invoice_id, $this->order_response['id'], $this->invoice_or_request_payment_url);
					break;

				}
			}
		}

		return $this;

	}

	/**
	 * verifyAuthenticity function
	 *
	 * @param Request $request
	 * @param string $webhook_id
	 * @return boolean
	 */
	private function verifyAuthenticity(Request $request, string $webhook_id) : bool {

		$headers = collect($request->headers->all())->map(fn($value) => $value[0])->all();

		$valid = $this->provider->verifyWebHookLocally($headers, $webhook_id, $request->getContent());

		return $valid;

	}

	/**
	 * getInvoiceOrRequestData function
	 *
	 * @param boolean $is_payment_request
	 * @return array
	 */
	public function getInvoiceOrRequestData(bool $is_payment_request = false) : array {

		$this->setInvoiceOrRequestData($is_payment_request);

		return [
			'order_response_id'				=>	$this->order_response_id,
			'invoice_or_request_payment_url'=>	$this->invoice_or_request_payment_url,
		];
	}

	/**
	 * ensurePaypalProduct function
	 *
	 * @return string
	 */
	private function ensurePaypalProduct() : string {

		$id = config('global.paypal_product_id');

		$existing = $this->provider->showProductDetails($id);

		if(isset($existing['id'])){
			return $existing['id']; // exists, reuse it
		}

		if(($existing['error']['name'] ?? null) === 'RESOURCE_NOT_FOUND'){
			$product = $this->provider->createProduct([
				'id'       => $id,
				'name'     => 'Recurring Subscription',
				'type'     => 'SERVICE',
				'category' => 'SOFTWARE',
			], 'product-main');

			if(!isset($product['id'])){
				throw new \RuntimeException('PayPal product create failed: ' . json_encode($product));
			}

			return $product['id'];
		}

		throw new \RuntimeException('Unexpected PayPal response: ' . json_encode($existing));

	}

	/**
	 * createPlan function
	 *
	 * @param string $product_id
	 * @param array $recurring_invoice
	 * @return string
	 */
	private function createPlan(string $product_id, array $recurring_invoice) : string {

		$freqency_array = Frequencies::getForPaypalFromValue((int) $recurring_invoice['frequency'], (int) $recurring_invoice['custom_frequency_days']);

		if(empty($freqency_array)){
			throw new \RuntimeException('Unexpected freqency_array value');
		}


		$plan = $this->provider->createPlan([
			'product_id' => $product_id,
			'name'       => 'Subscription Plan',
			'status'     => 'ACTIVE',
			'billing_cycles' => [
				[
					'frequency'      => $freqency_array,
					'tenure_type'    => 'REGULAR',
					'sequence'       => 1,
					'total_cycles'   => 0,
					'pricing_scheme' => [
						'fixed_price' => ['value' => $recurring_invoice['total'], 'currency_code' => $recurring_invoice['code']],
					],
				],
			],
			'payment_preferences' => [
				'auto_bill_outstanding'     => true,
				'setup_fee'                 => ['value' => '0', 'currency_code' => $recurring_invoice['code']],
				'setup_fee_failure_action'  => 'CONTINUE',
				'payment_failure_threshold' => 3,
			],
		]);
		
		$plan_id = $plan['id'];
		return $plan_id;

	}

	/**
	 * fetchPendingApproveUrl function
	 *
	 * @param string $plan_id
	 * @param string $gateway_subscription_id
	 * @return string|null
	 */
	private function fetchPendingApproveUrl(string $plan_id, string $gateway_subscription_id): ?string {

		try{

			$plan = $this->provider->showPlanDetails($plan_id);

			if(($plan['status'] ?? null) !== 'ACTIVE'){
				return null;
			}

			$sub = $this->provider->showSubscriptionDetails($gateway_subscription_id);

			if(($sub['status'] ?? null) !== 'APPROVAL_PENDING'){
				return null;
			}

			$approve = collect($sub['links'] ?? [])->firstWhere('rel', 'approve');

			return $approve['href'] ?? null;

		}catch(\Throwable $e){
			return null;
		}

	}

	/**
	 * insertRecurringInvoiceUrl function
	 *
	 * @param string $url
	 * @return boolean
	 */
	private function insertRecurringInvoiceUrl(string $url, array $recurring_invoice) : bool {
		return $this->db->insertRecurringInvoiceUrl([
			'company_id'				=>	$recurring_invoice['company_id'],
			'recurring_invoice_id'		=>	$recurring_invoice['id'],
			'payment_gateway'			=>	$recurring_invoice['payment_gateway'],
			'total'						=>	$recurring_invoice['total'],
			'currency_id'				=>	$recurring_invoice['currency_id'],
			'url'						=>	$url
		]);
	}

	/**
	 * createSubscription function
	 *
	 * @param string $plan_id
	 * @return array
	 */
	private function createSubscription(string $plan_id, array $recurring_invoice) : array {

		$response = $this->provider->createSubscription([
			'plan_id'    => $plan_id,
			'custom_id'  => (string) $recurring_invoice['id'],
			'subscriber' => [
				'name' => [
					'given_name' => $recurring_invoice['first_name'], //recurring invoice will have table join with clients.
					'surname'    => $recurring_invoice['last_name'],
				],
				'email_address' => $recurring_invoice['email'],
			],
			'application_context' => [
				'brand_name'          => config('app.name'),
				'locale'              => 'en-US',
				'shipping_preference' => 'NO_SHIPPING',
				'user_action'         => 'SUBSCRIBE_NOW',
				'payment_method'      => [
					'payer_selected'  => 'PAYPAL',
					'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
				],
				"cancel_url" => env('APP_URL').PAYMENT_CANCEL_URL,
				"return_url" => env('APP_URL').PAYMENT_SUCCESS_URL
			],
		]);

		if(!isset($response['id'])){
			throw new \RuntimeException('PayPal subscription create failed: ' . json_encode($response));
		}
		
		$approve_url = collect($response['links'])->firstWhere('rel', 'approve')['href'] ?? null;

		if(!$approve_url){
			throw new \RuntimeException('No approve link: ' . json_encode($response));
		}

		return [
			'url'	=>	$approve_url,
			'id'	=>	$response['id'],
		];

	}

	/**
	 * getSubscriptionUrl function
	 *
	 * @return string
	 */
	public function getSubscriptionUrl() : string {

		$data = $this->dto->getData();
		$recurring_invoice = $data['recurring_invoice']->toArray();

		$product_id = $data['product_id'];

		$plan = $this->db->fetchSubscriptionPlanByRI((int) $recurring_invoice['company_id'], (int) $recurring_invoice['id'], (int) $recurring_invoice['payment_gateway']);

		if($plan){

			$url_exists = $this->fetchPendingApproveUrl($plan->plan_id, $plan->gateway_subscription_id);
			
			if($url_exists){

				$this->insertRecurringInvoiceUrl($url_exists, $recurring_invoice);
				return $url_exists;

			}
		}

		$product_id = $this->ensurePaypalProduct();

		//makes api call to paypal
		$plan_id = $this->createPlan($product_id, $recurring_invoice);
		//makes api call to paypal
		$sub_data = $this->createSubscription($plan_id, $recurring_invoice);

		$data = [
			'company_id'				=>	$recurring_invoice['company_id'],
			'recurring_invoice_id'		=>	$recurring_invoice['id'],
			'payment_gateway'			=>	$recurring_invoice['payment_gateway'],
			'gateway_subscription_id'	=>	$sub_data['id'],
			'plan_id'					=>	$plan_id,
			'payer_id'					=>	'',
			'customer_id'				=>	'',
		];

		return DB::transaction(function () use ($data, $sub_data, $recurring_invoice) {

			$this->db->insertSubScriptionPlan($data);

			$this->insertRecurringInvoiceUrl($sub_data['url'], $recurring_invoice);

			return $sub_data['url'];

		});

	}
	
	/**
	 * handleWebhook function
	 *
	 * @param Request $request
	 * @return bool
	 */
	public function handleWebhook(Request $request) : bool {

		$request_data = $request->all();

		$data = $this->db->fetchPayPalSettings($request_data);

		if(!$this->verifyAuthenticity($request, $this->webhook_id)){
			logger("unauthorized request made -> \n".json_encode($request_data));
			return false;
		}

		$event_type = $request_data['event_type'] ?? null;

		if(!in_array((string) $event_type, PayPalEvents::getAll())){
			logger("event type ".$event_type." is not added in the application");
			return false;
		}

		try{

			if((string) $event_type === PayPalEvents::CHECKOUT_APPROVED->value){
				$this->provider->capturePaymentOrder($data['order_id']);
			}

		}catch(Exception $e){
			logger("failed to create payment order");
		}
		
		if((string) $event_type === PayPalEvents::PAYMENT_CAPTURED->value){

			$status = $request_data['resource']['status'];

			if($status !== 'COMPLETED'){
				return true;
			}

			$transaction = $this->db->fetchTransactionByTokenId($data['order_id']);
			
			if($transaction->is_payment_captured){
				return true;
			}

		}

		$transaction = $this->db->fetchTransactionByToken($data['order_id']);
		if(!$transaction){
			return false;
		}

		//update db
		if((string) $event_type === PayPalEvents::CHECKOUT_APPROVED->value){
			return $this->db->updatePayPalOrderApproved($transaction, $request_data);
		}else if((string) $event_type === PayPalEvents::PAYMENT_CAPTURED->value){
			return $this->db->updatePayPalPaymentCaputred($transaction, $request_data);
		}else if((string) $event_type === PayPalEvents::PAYMENT_PENDING->value){
			return $this->db->updatePayPalPending($transaction, $request_data);
		}

		return false;

	}


}