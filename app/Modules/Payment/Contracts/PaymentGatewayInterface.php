<?php

namespace App\Modules\Payment\Contracts;

interface PaymentGatewayInterface{

	/**
	 * generateUrl function
	 *
	 * @return string
	 */
	public function generateUrl() : string;

}