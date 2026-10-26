<?php

namespace App\Modules\Payment\Gateways\PayPal;

use Srmklive\PayPal\Services\PayPal as Provider;

class Credentials {

	public function __construct(
		private string $paypal_client_id,
		private string $paypal_app_id,
		private string $secret,
		private string $paypal_mode,
		private string $currency
	){}


	/**
	 * credsArray function
	 *
	 * @return array
	 */
	private function credsArray() : array {
		return [
			'payment_action'	=>	'Sale',
			'currency'			=>	$this->currency,
			'notify_url'		=>	'',
			'validate_ssl'		=>	true,
			'mode'				=>	$this->paypal_mode,
			'locale'			=>	'en_US',
			'sandbox' => [
				'client_id'         => $this->paypal_client_id,
				'client_secret'     => $this->secret,
				'app_id'            => $this->paypal_app_id
			],
			'live' => [
				'client_id'         => $this->paypal_client_id,
				'client_secret'     => $this->secret,
				'app_id'            => $this->paypal_app_id
			],
		];
	}


	/**
	 * getProvider function
	 *
	 * @return Provider
	 */
	public function getProvider() : Provider {
		return new Provider($this->credsArray());
	}

}