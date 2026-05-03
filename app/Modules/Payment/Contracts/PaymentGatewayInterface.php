<?php

namespace App\Modules\Payment\Contracts;

interface PaymentGatewayInterface{

	/**
	 * generateUrl function
	 *
	 * @return string|null
	 */
	public function generateUrl() : ?string;

	/**
	 * handlePayment function
	 *
	 * @return boolean
	 */
	public function handlePayment() : bool;

}