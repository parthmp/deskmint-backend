<?php

namespace App\Modules\Payment\Gateways\PayPal\Implementation;

use Srmklive\PayPal\Services\PayPal as Provider;

class Credentials {

	public function __construct(
		private string $paypal_client_id,
		private string $paypal_app_id,
		private string $secret,
		private string $mode,
		private string $currency,
		private string $webhook_id
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
			'mode'				=>	$this->mode,
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

	/**
	 * getWebhookId function
	 *
	 * @return string
	 */
	public function getWebhookId() : string {
		return $this->webhook_id;
	}

}