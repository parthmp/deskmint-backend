<?php

namespace App\Modules\Payment\Gateways\PayPal;
use Srmklive\PayPal\Services\PayPal as Provider;

class PayPal {

	//gateway_mode means
	//invoice, recurring_invoice or payment request.
	public function __construct(
		private int $gateway_mode
	){}


	


}