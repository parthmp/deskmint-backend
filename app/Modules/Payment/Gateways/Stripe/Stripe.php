<?php

namespace App\Modules\Payment\Gateways\Stripe;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Jobs\FetchStripeBalanceTransactionJob;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Brick\Math\RoundingMode;
use Exception;
use Stripe\StripeClient;
use \Illuminate\Support\Str;

class Stripe implements PaymentGatewayInterface{

	private StripeClient $stripe_client;
	private DatabaseOperations $database_operations;
	private Invoice $invoice;
	private int $amount_in_cents;
	private string $webhook_secret;

	public function __construct(
		private string $invoice_id,
		private string $secret,
		private string $currency,
		private float $amount,
		?DatabaseOperations $database_operations = null
	){
		$this->stripe_client = new StripeClient($secret);
		$this->database_operations = $database_operations ?? new DatabaseOperations(new SettingsSectionRepository());
		$this->invoice = $this->database_operations->fetchInvoiceById($this->invoice_id);
		$this->amount_in_cents = BigDecimal::of($this->amount)->multipliedBy(100)->toScale(2, RoundingMode::HalfUp)->toInt();
	}

	/**
	 * setWebhookSecret function
	 *
	 * @param string $webhook_secret
	 * @return void
	 */
	public function setWebhookSecret(string $webhook_secret) : void {
		$this->webhook_secret = $webhook_secret;
	}

	/**
	 * createTransaction function
	 *
	 * @param string $order_id
	 * @return Transaction
	 */
	private function createTransaction(string $order_id) : Transaction {
		return $this->database_operations->insertTransaction([
			'invoice_id'					=>	$this->invoice_id,
			'amount'						=>	$this->amount,
			'payment_method'				=>	PAYMENT_STRIPE,
			'mode'							=>	'-',
			'token_id_identifier'			=>	$order_id,
			'is_approved'					=>	0,
			'is_payment_captured'			=>	0
		]);
	}

	public function generateUrl() : ?string {

		$payment_id = Str::uuid();
		
		$transaction = $this->createTransaction($payment_id);

		try{

			$session = $this->stripe_client->checkout->sessions->create([
				'line_items' => [[
					'price_data' => [
						'currency' => $this->currency,
						'product_data' => [
							'name' => 'Invoice # '.$this->invoice->invoice_number,
							'description' => 'Payment for invoice # '.$this->invoice->invoice_number,
						],
						'unit_amount' => $this->amount_in_cents, // Amount in cents ($20.00)
					],
					'quantity' => 1,
				]],
				'mode' => 'payment', // Use 'subscription' for recurring billing
				'success_url' 	=> env('APP_URL').PAYMENT_SUCCESS_URL,
				'cancel_url' 	=> env('APP_URL').PAYMENT_CANCEL_URL,
				'metadata' => [
					'payment_id' => $payment_id
				]
			]);

		}catch(Exception $e){
			logger(json_encode($e->getMessage()));
			throw new PaymentException("Unable to generate payment url", "url_generation_failed", config('global.error_code'));
		}

		$checkout_url = $session->url;

		$this->database_operations->insertPaymentUrl($transaction->id, $payment_id, $checkout_url);
	
		return $checkout_url;
		
	}

	/**
	 * verifyAuthenticity function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	private function verifyAuthenticity(Request $request) : bool {

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

	public function handlePayment(array $data, Request $request): bool {

		if(!$this->verifyAuthenticity($request)){
			throw new PaymentException('unauthorized', 'unauthorized', 401);
		}

		$event_type = $data['type'] ?? null;

		if((string) $event_type === 'checkout.session.completed'){

			$order_id = $data['order_id'];
			$transaction = $this->database_operations->fetchTransactionByTokenId($order_id);

			if($transaction->is_payment_captured){
				return true;
			}

			$payment_status = $data['data']['object']['payment_status'];

			if($payment_status !== 'paid'){
				return true;
			}

			$payment_intent_id = $data['data']['object']['payment_intent'];

			$payment_intent = $this->stripe_client->paymentIntents->retrieve($payment_intent_id, [
				'expand' => ['latest_charge.balance_transaction']
			]);

			$balance_transaction = $payment_intent->latest_charge->balance_transaction;

			$gateway_fee = '0';
			$net_amount  = '0';

			if($balance_transaction){

				$gateway_fee = BigDecimal::of($balance_transaction->fee)->dividedBy(100, 2, RoundingMode::HalfUp)->__toString();

				$net_amount = BigDecimal::of($balance_transaction->net)->dividedBy(100, 2, RoundingMode::HalfUp)->__toString();

				if($balance_transaction->currency !== strtolower($this->currency)){

					$exchange_rate = BigDecimal::of($balance_transaction->exchange_rate);
					$gateway_fee = BigDecimal::of($gateway_fee)->dividedBy($exchange_rate, 2, RoundingMode::HalfUp)->__toString();
					$net_amount = BigDecimal::of($net_amount)->dividedBy($exchange_rate, 2, RoundingMode::HalfUp)->__toString();

				}

			}else{
				FetchStripeBalanceTransactionJob::dispatch($payment_intent_id, $transaction->id, $this->secret, $this->currency)->delay(10);
			}
			
			$data['gateway_fees_amount'] = $gateway_fee;
			$data['received_amount'] = $net_amount;

			return $this->database_operations->updateStripePaymentTransaction($data);

		}

		throw new PaymentException("Unsupported event", "unsupported_event", config('global.error_code'));

	}

}