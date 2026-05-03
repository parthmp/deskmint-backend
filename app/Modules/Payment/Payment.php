<?php

namespace App\Modules\Payment;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;

class Payment{

	public function __construct(private PaymentGatewayInterface $payment_gateway){}

	/**
	 * paymentURL function
	 *
	 * @return null|string
	 */
	public function paymentURL() : ?string {
		return $this->payment_gateway->generateUrl();
	}

	/**
	 * handlePayment function
	 *
	 * @return boolean
	 */
	public function handlePayment() : bool {
		return $this->payment_gateway->handlePayment();
	}

}