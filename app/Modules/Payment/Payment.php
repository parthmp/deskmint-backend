<?php

namespace App\Modules\Payment;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;

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
	 * @param array $data
	 * @return boolean
	 */
	public function handlePayment(array $data, Request $request) : bool {
		return $this->payment_gateway->handlePayment($data, $request);
	}

	/**
	 * updateUrl function
	 *
	 * @param string $gateway_url_identifier
	 * @return boolean
	 */
	public function updateUrl(string $gateway_url_identifier) : bool {
		return $this->payment_gateway->updateUrl($gateway_url_identifier);
	}

}