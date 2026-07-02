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
	 * updateUrl function
	 *
	 * @param string $gateway_url_identifier
	 * @return boolean
	 */
	public function updateUrl(string $gateway_url_identifier) : bool;

	/**
	 * handlePayment function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function handlePayment(array $data, Request $request) : bool;

}