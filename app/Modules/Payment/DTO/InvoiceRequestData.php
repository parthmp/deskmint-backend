<?php

namespace App\Modules\Payment\DTO;

use App\Modules\Payment\Contracts\GenericDTO;
use Override;

class InvoiceRequestData implements GenericDTO {

	public function __construct(
		public int $currency_id,
		public int $company_id,
		public string $amount,
		public string $mode,
		public ?int $payment_request_id,
		public ?int $invoice_id,
		public int $client_id
	){}

	#[Override]
	public function getData(): array
	{
		return [
			'currency_id'			=>	$this->currency_id,
			'company_id'			=>	$this->company_id,
			'amount'				=>	$this->amount,
			'mode'					=>	$this->mode,
			'payment_request_id'	=>	$this->payment_request_id,
			'invoice_id'			=>	$this->invoice_id,
			'client_id'				=>	$this->client_id
		];
	}

}