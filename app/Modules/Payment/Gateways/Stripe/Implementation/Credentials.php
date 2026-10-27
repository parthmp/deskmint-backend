<?php

namespace App\Modules\Payment\Gateways\Stripe\Implementation;

use Stripe\StripeClient as Provider;

class Credentials {

	public function __construct(
		private string $api_secret,
		private string $webhook_secret
	){}

	/**
	 * getProvider function
	 *
	 * @return Provider
	 */
	public function getProvider() : Provider {
		return new Provider($this->api_secret);
	}
	

	/**
	 * getWebhookSecret function
	 *
	 * @return string
	 */
	public function getWebhookSecret() : string {
		return $this->webhook_secret;
	}

	/**
	 * getSecret function
	 *
	 * @return string
	 */
	public function getApiSecret() : string {
		return $this->api_secret;
	}

}