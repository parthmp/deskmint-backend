<?php

namespace App\Modules\Payment\Gateways\PayPal\Implementation;

use Srmklive\PayPal\Services\PayPal as Provider;

class Order {

	public function __construct(
		private string $currency_code,
		private string $amount
	){}

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
						"currency_code" => $this->currency_code,
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
	 * createOrder function
	 *
	 * @param Provider $provider
	 * @return array
	 */
	public function createOrder(Provider $provider) : array {
		return $provider->createOrder($this->orderData());
	}

}