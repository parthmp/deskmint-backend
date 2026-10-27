<?php

namespace App\Modules\Payment\DTO;

use App\Models\RecurringInvoice;
use App\Modules\Payment\Contracts\GenericDTO;
use Override;

class PayPalSubscriptionData implements GenericDTO {

	public function __construct(
		public string $product_id,
		public RecurringInvoice $recurring_invoice 
	){}

	#[Override]
	public function getData(): array
	{
		return [
			'product_id'		=>	$this->product_id,
			'recurring_invoice'	=>	$this->recurring_invoice
		];
	}

}