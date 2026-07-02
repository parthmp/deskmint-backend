<?php

namespace App\Modules\Payment\Contracts;

use Illuminate\Http\Request;

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
	 * @param array $data
	 * @return boolean
	 */
	public function handlePayment(array $data, Request $request) : bool;

}