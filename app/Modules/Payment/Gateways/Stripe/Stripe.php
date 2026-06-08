<?php

namespace App\Modules\Payment\Gateways\Stripe;

use App\Models\Invoice;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Exceptions\PaymentException;
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
		$this->amount_in_cents = BigDecimal::of($this->amount)->multipliedBy(100)->toScale(2, RoundingMode::HALF_UP)->toInt();
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
	 * @return boolean
	 */
	private function createTransaction(string $order_id) : bool {
		return $this->database_operations->insertTransaction([
			'invoice_id'					=>	$this->invoice_id,
			'amount'						=>	$this->amount,
			'payment_method'				=>	PAYMENT_STRIPE,
			'mode'							=>	'-',
			'token_id_identifier'			=>	$order_id,
			'payment_approved_details'		=>	null,
			'payment_captured_details'		=>	null,
			'is_approved'					=>	0,
			'is_payment_captured'			=>	0
		]);
	}

	public function generateUrl() : ?string {

		$payment_id = Str::uuid();
		
		$this->createTransaction($payment_id);

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
			return $this->database_operations->updateStripePaymentTransaction($data);
		}

		throw new PaymentException("Unsupported event", "unsupported_event", config('global.error_code'));
		
	}

}