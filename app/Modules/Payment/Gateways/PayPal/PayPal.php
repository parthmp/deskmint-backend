<?php

namespace App\Modules\Payment\Gateways\PayPal;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Transactions;
use Override;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPal implements PaymentGatewayInterface{

	private Transactions $transactions;

	public function __construct(
		private string $invoice_id,
		private string $client_id,
		private string $app_id,
		private string $secret,
		private string $mode,
		private string $currency,
		private float $amount
	){
		$this->transactions = new Transactions(new DatabaseOperations());
	}

	/**
	 * wireUpCreds function
	 *
	 * @return array
	 */
	private function wireUpCreds() : array {
		return [
			'payment_action'	=>	'Sale',
			'currency'			=>	$this->currency,
			//'notify_url'		=>	env('APP_URL').PAYMENT_NOTIFICATION_URL,
			'notify_url'		=>	'',
			'validate_ssl'		=>	true,
			'mode'				=>	$this->mode,
			'locale'			=>	'en_US',
			'sandbox' => [
				'client_id'         => $this->client_id,
				'client_secret'     => $this->secret,
				'app_id'            => $this->app_id
			],
			'live' => [
				'client_id'         => $this->client_id,
				'client_secret'     => $this->secret,
				'app_id'            => $this->app_id
			],
		];
	}

	/**
	 * orderData function
	 *
	 * @return array
	 */
	private function orderData() : array {
		return [
			"intent" => "CAPTURE",
			"purchase_units" => [
				[
					"amount" => [
						"currency_code" => $this->currency,
						"value" 		=> $this->amount
					]
				]
			],
			"application_context" => [
				"cancel_url" => env('APP_URL').PAYMENT_CANCEL_URL,
				"return_url" => env('APP_URL').PAYMENT_SUCCESS_URL,
			]
		];
	}

	/**
	 * createTransaction function
	 *
	 * @param string $order_id
	 * @return boolean
	 */
	private function createTransaction(string $order_id) : bool {
		return $this->transactions->create([
			'invoice_id'			=>	$this->invoice_id,
			'amount'				=>	$this->amount,
			'payment_method'		=>	PAYMENT_PAYPAL,
			'mode'					=>	$this->mode,
			'token_id_identifier'	=>	$order_id,
			'additional_details'	=>	null,
			'is_success'			=>	0
		]);
	}

	/**
	 * generateURL function
	 *
	 * @return string|null
	 */
	public function generateURL() : ?string {
		
		$provider = new PayPalClient($this->wireUpCreds());
		
		$paypal_token = $provider->getAccessToken();

		$response = $provider->createOrder($this->orderData());
		
		if(isset($response['id']) && $response['id'] != null){
			foreach($response['links'] as $link) {
				if($link['rel'] === 'approve'){
					$this->createTransaction($response['id']);
					return $link['href'];
				}
			}
		}

		return null;
		
   	}

	/**
	 * handlePayment function
	 *
	 * @return boolean
	 */
	public function handlePayment() : bool {
		
	}

}