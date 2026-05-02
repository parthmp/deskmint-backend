<?php

namespace App\Modules\Payment\Gateways\PayPal;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPal implements PaymentGatewayInterface{

	public function __construct(
		private string $client_id,
		private string $app_id,
		private string $secret,
		private string $mode,
		private string $currency,
		private float $amount,
	){}

	/**
	 * wireUpCreds function
	 *
	 * @return array
	 */
	private function wireUpCreds() : array {
		return [
			'payment_action'	=>	'Sale',
			'currency'			=>	$this->currency,
			'notify_url'		=>	env('APP_URL').PAYMENT_NOTIFICATION_URL,
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
	 * generateURL function
	 *
	 * @return string|null
	 */
	public function generateURL() : ?string {
		
		$provider = new PayPalClient($this->wireUpCreds());
		//$provider->setApiCredentials($this->wireUpCreds());

		//$paypal_token = $provider->getAccessToken();

		$response = $provider->createOrder($this->orderData());
		return json_encode($response).'====';
		if(isset($response['id']) && $response['id'] != null){
			foreach($response['links'] as $link) {
				if($link['rel'] === 'approve') {
					return $link['href'];
				}
			}
		}

		return null;
		
   	}


}