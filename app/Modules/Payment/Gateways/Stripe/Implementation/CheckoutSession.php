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
		private string $uuid,
		private int $recurring_invoice_id = 0,
		private string $interval = 'month',
		private int $interval_count = 1
	){}

	/**
	 * create function
	 *
	 * @return \Stripe\Checkout\Session
	 */
	public function create() : \Stripe\Checkout\Session {

		$product_id = (new ProductResolver($this->provider))->id();

		$is_subscription = $this->recurring_invoice_id > 0;

		$product_data = ['name' => $this->name];
		if($this->description !== ''){
			$product_data['description'] = $this->description;
		}

		// $price_data = [
		// 	'currency'     => strtolower($this->currency_code),
		// 	'product_data' => $product_data,
		// 	'unit_amount'  => $this->cents,
		// ];

		$price_data = [
			'currency'    => strtolower($this->currency_code),
			'product'     => $product_id,
			'unit_amount' => $this->cents,
		];

		if($is_subscription){
			$price_data['recurring'] = [
				'interval'       => strtolower($this->interval),
				'interval_count' => $this->interval_count,
			];
		}

		$params = [
			'mode'        => $is_subscription ? 'subscription' : 'payment',
			'line_items'  => [[
				'price_data' => $price_data,
				'quantity'   => 1,
			]],
			'success_url' => env('APP_URL') . PAYMENT_SUCCESS_URL,
			'cancel_url'  => env('APP_URL') . PAYMENT_CANCEL_URL,
			'client_reference_id' => $this->uuid,
			//lands on the Checkout Session (checkout.session.completed)
			'metadata' => [
				'order_id'             => $this->uuid,
				'recurring_invoice_id' => (string) $this->recurring_invoice_id,
			],
		];

		if($is_subscription){
			//lands on the Subscription and on every renewal invoice
			$params['subscription_data'] = [
				'metadata' => [
					'order_id'             => $this->uuid,
					'recurring_invoice_id' => (string) $this->recurring_invoice_id,
				],
			];
		}else{
			//lands on the PaymentIntent / charge
			$params['payment_intent_data'] = [
				'metadata' => [
					'order_id' => $this->uuid,
				],
			];
		}

		return $this->provider->checkout->sessions->create($params);
	}

}