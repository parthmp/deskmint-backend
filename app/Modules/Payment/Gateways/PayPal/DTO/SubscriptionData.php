<?php

namespace App\Modules\Payment\Gateways\PayPal\DTO;

use App\Models\RecurringInvoice;
use App\Modules\Payment\Contracts\GenericDTO;
use Override;

class SubscriptionData implements GenericDTO {

	public function __construct(
		public RecurringInvoice $recurring_invoice 
	){}

	#[Override]
	public function getData(): array
	{
		return [
			'recurring_invoice'	=>	$this->recurring_invoice
		];
	}

}