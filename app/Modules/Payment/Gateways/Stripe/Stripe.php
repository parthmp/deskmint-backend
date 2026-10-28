<?php

namespace App\Modules\Payment\Gateways\Stripe;

use App\Enums\RecurringInvoices\Frequencies;
use App\Exceptions\PaymentException;
use App\Helpers\General;
use App\Models\Transaction;
use App\Modules\Payment\Contracts\GenericDTO;
use App\Modules\Payment\Contracts\PaymentInterface;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Enums\StripeEvents;
use App\Modules\Payment\Enums\TransactionStatus;
use App\Modules\Payment\Gateways\Stripe\Implementation\CheckoutSession;
use App\Modules\Payment\Gateways\Stripe\Implementation\Credentials;
use App\Modules\Payment\Jobs\FetchStripeBalanceTransactionJob;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient as Provider;
use Illuminate\Support\Str;

class Stripe implements PaymentInterface {

	private DatabaseOperations $db;
	private ?Provider $provider = null;
	private string $webhook_secret;
	private string $currency_code;
	private string $api_secret;
	private ?GenericDTO $dto = null;

	public function __construct(){
		$this->db = $this->getDB();
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
	 * setCredentials function
	 *
	 * @param Credentials $credentials
	 * @return self
	 */
	public function setCredentials(Credentials $credentials) : self {
		$this->provider = $credentials->getProvider();
		$this->webhook_secret = $credentials->getWebhookSecret();
		$this->api_secret = $credentials->getApiSecret();
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
	 * createTransaction function
	 *
	 * @param string $order_id
	 * @return Transaction
	 */
	private function createTransaction(string $order_id, array $data) : Transaction {
		return $this->db->insertTransaction([
			'company_id'					=>	$data['company_id'],
			'currency_id'					=>	$data['currency_id'],
			'amount'						=>	$data['amount'],
			'payment_gateway'				=>	PaymentGateway::STRIPE->value,
			'mode'							=>	'-',
			'token_id_identifier'			=>	$order_id,
			'is_approved'					=>	0,
			'is_payment_captured'			=>	0,
			'status'						=>	(int) TransactionStatus::PENDING->value
		]);
	}
	
	/**
	 * getInvoiceOrRequestData function
	 *
	 * @param boolean $is_payment_request
	 * @return array
	 */
	public function getInvoiceOrRequestData(bool $is_payment_request = false) : array {

		$uuid_token = Str::uuid();

		$data = $this->dto->getData();
		
		try{

			$invoice_id = null;
			$payment_request_id = null;

			if($is_payment_request){

				if(!$data['payment_request_id']){
					throw new PaymentException("payment request id not provided.");
				}

				$payment_request_id = (int) $data['payment_request_id'];

				$name = '# '.$uuid_token;
				$desc = 'Payment for request # '.$uuid_token;


			}else{

				if(!$data['invoice_id']){
					throw new PaymentException("invoice id not provided.");
				}

				$invoice_id = (int) $data['invoice_id'];

				$name = 'Invoice # '.$data['invoice_number'];
				$desc = 'Payment for invoice # '.$data['invoice_number'];
			}


			$checkout_session = new CheckoutSession($this->provider, $data['currency_code'], $name, $desc, $data['amount_in_cents'], $uuid_token);
			$session = $checkout_session->create();

			$transaction = $this->createTransaction($uuid_token, $data);
			$this->db->upsertTransactionReference((int) $data['company_id'], (int) $data['client_id'], $invoice_id, $payment_request_id, (int) $transaction->id);
			$this->db->insertEmptyTransactionGatewayDetails($transaction->id);

		}catch(Exception $e){
			logger(json_encode($e->getMessage()));
			return [];
			//throw new PaymentException("Unable to generate payment url", "url_generation_failed", config('global.error_code'));
		}

		$checkout_url = $session->url;

		$this->db->insertPaymentUrl($transaction->id, $data['invoice_id'], $uuid_token, $checkout_url);
	
		return [
			'order_response_id'				=>	$uuid_token,
			'invoice_or_request_payment_url'=>	$checkout_url,
		];
		
	}

	/**
	 * verifyAuthenticity function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	protected function verifyAuthenticity(Request $request) : bool {

		$payload = $request->getContent();
		$sig_header = $request->header('Stripe-Signature');
		$webhook_secret = $this->webhook_secret;

		try{

			$event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
			return true;

		}catch(\Stripe\Exception\SignatureVerificationException $e){
			return false;
		}

	}

	/**
	 * handleWebhook function
	 *
	 * @param Request $request
	 * @param array $settings_data
	 * @return boolean
	 */
	public function handleWebhook(Request $request, array $settings_data) : bool {

		$request_data = $request->all();
		
		if(!$this->verifyAuthenticity($request)){
			logger("unauthorized request made -> \n".json_encode($request_data));
			return false;
		}

	
		$event_type = $request_data['type'] ?? null;
		
		if(!in_array((string) $event_type, StripeEvents::getAll())){
			logger("event type ".$event_type." is not added in the application");
			return false;
		}
		
		if((string) $event_type === StripeEvents::CHECKOUT_COMPLETED->value){
			
			$data = $settings_data;

			$order_id = $data['order_id'];
			$transaction = $this->db->fetchTransactionByToken($order_id);

			if(!$transaction){
				return false;
			}

			if($transaction->is_payment_captured){
				return true;
			}

			$payment_status = $request_data['data']['object']['payment_status'];

			if($payment_status !== 'paid'){
				return true;
			}
			
			$payment_intent_id = $request_data['data']['object']['payment_intent'];
			
			$payment_intent = $this->provider->paymentIntents->retrieve($payment_intent_id, [
				'expand' => ['latest_charge.balance_transaction']
			]);
			
			$balance_transaction = $payment_intent->latest_charge->balance_transaction;
			
			$gateway_fee = '0';
			$net_amount  = '0';
			
			if($balance_transaction){
				
				$gateway_fee = BigDecimal::of($balance_transaction->fee)->dividedBy(100, 2, RoundingMode::HalfUp)->__toString();

				$net_amount = BigDecimal::of($balance_transaction->net)->dividedBy(100, 2, RoundingMode::HalfUp)->__toString();
				
				if($balance_transaction->currency !== strtolower($data['webhook_data']['currency_code'])){
					
					$exchange_rate = BigDecimal::of($balance_transaction->exchange_rate);
					$gateway_fee = BigDecimal::of($gateway_fee)->dividedBy($exchange_rate, 2, RoundingMode::HalfUp)->__toString();
					$net_amount = BigDecimal::of($net_amount)->dividedBy($exchange_rate, 2, RoundingMode::HalfUp)->__toString();

				}

			}else{
				
				FetchStripeBalanceTransactionJob::dispatch($payment_intent_id, $transaction->id, $this->api_secret, $data['webhook_data']['currency_code'])->delay(15);
			}

			$request_data['gateway_fees_amount'] = $gateway_fee;
			$request_data['received_amount'] = $net_amount;
			$request_data['order_id'] = $order_id;
			
			$res = $this->db->updateStripePaymentTransaction($request_data);
			
			return $res;

		}

		return false;

	}

	/**
	 * getExiststingSubscriptionUrl function
	 *
	 * @param integer $recurring_invoice_id
	 * @return string|null
	 */
	private function getExiststingSubscriptionUrl(int $recurring_invoice_id) : ?string {

		$query = "metadata['recurring_invoice_id']:'" . $recurring_invoice_id . "' AND status:'active'";

		$existing = $this->provider->subscriptions->search([
			'query' => $query,
			'limit' => 1,
		]);

		if(!empty($existing->data)){
			
			$subscription = $existing->data[0];
			
			$portalSession = $this->provider->billingPortal->sessions->create([
				'customer' 		=> 	$subscription->customer,
				'return_url' 	=> env('APP_URL').PAYMENT_CANCEL_URL,
			]);

			$url = $portalSession->url;
			
			return $url;
		}

		return null;

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
	 * getSubscriptionUrl function
	 *
	 * @return string
	 */
	public function getSubscriptionUrl() : string {

		$data = $this->dto->getData();
		$recurring_invoice = $data['recurring_invoice']->toArray();

		$plan = $this->db->fetchSubscriptionPlanByRI((int) $recurring_invoice['company_id'], (int) $recurring_invoice['id'], (int) $recurring_invoice['payment_gateway']);
		
		if($plan){

			$existing_url = $this->getExiststingSubscriptionUrl((int) $recurring_invoice['id']);
			if($existing_url){
				$this->insertRecurringInvoiceUrl((string) $existing_url, $recurring_invoice);
				return $existing_url;
			}

		}
		

		$cents = General::toCents($recurring_invoice['total']);

		$freqency_array = Frequencies::getForGatewayFromValue((int) $recurring_invoice['frequency'], (int) $recurring_invoice['custom_frequency_days']);

		$session = $this->provider->checkout->sessions->create([
			'success_url' =>  env('APP_URL').PAYMENT_SUCCESS_URL,
			'cancel_url'  =>  env('APP_URL').PAYMENT_CANCEL_URL,
			'mode'        => 'subscription',
			'subscription_data' => [
				'metadata' => [
					'recurring_invoice_id' => $recurring_invoice['id']
				],
			],
			'line_items'  => [
				[
					'price_data' => [
						'currency' => (string) $recurring_invoice['code'],
						'unit_amount' => $cents,
						'recurring' => [
							'interval' => strtolower($freqency_array['interval_unit']), // 'day', 'week', 'month', or 'year'
							'interval_count' => (int) $freqency_array['interval_count'],  // e.g., every 1 month
						],
						'product_data' => [
							'name' => 'Subscription Plan',
						],
					],
					'quantity' => 1,
				],
			],
		]);

		$data = [
			'company_id'				=>	$recurring_invoice['company_id'],
			'recurring_invoice_id'		=>	$recurring_invoice['id'],
			'payment_gateway'			=>	$recurring_invoice['payment_gateway'],
			'gateway_subscription_id'	=>	'', //update this after customer approves.
			'plan_id'					=>	'',
			'payer_id'					=>	''
		];

		$url = $session->url;

		return DB::transaction(function () use ($data, $url, $recurring_invoice) {

			$this->db->upsertSubScriptionPlan($data);

			$this->insertRecurringInvoiceUrl((string) $url, $recurring_invoice);

			return $url;

		});

	}


}