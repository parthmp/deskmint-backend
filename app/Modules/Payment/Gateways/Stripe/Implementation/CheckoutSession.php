<?php

namespace App\Modules\Payment\Gateways\Stripe\Implementation;

use Stripe\StripeClient as Provider;

class CheckoutSession {

	public function __construct(
		private Provider $provider,
		private string $currency_code,
		private string $name,
		private string $description,
		private int $cents,
		private string $uuid
	){}

	/**
	 * create function
	 *
	 * @return \Stripe\Checkout\Session
	 */
	public function create() : \Stripe\Checkout\Session {
		return $this->provider->checkout->sessions->create([
				'line_items' => [[
					'price_data' => [
						'currency' => $this->currency_code,
						'product_data' => [
							'name' => $this->name,
							'description' => $this->description,
						],
						'unit_amount' => $this->cents, // Amount in cents ($20.00)
					],
					'quantity' => 1,
				]],
				'mode' => 'payment', // Use 'subscription' for recurring billing
				'success_url' 	=> env('APP_URL').PAYMENT_SUCCESS_URL,
				'cancel_url' 	=> env('APP_URL').PAYMENT_CANCEL_URL,
				'metadata' => [
					'order_id' => $this->uuid
				]
			]);
	}

}