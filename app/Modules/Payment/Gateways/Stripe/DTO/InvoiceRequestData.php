<?php

namespace App\Modules\Payment\Gateways\Stripe\DTO;

use App\Helpers\General;
use App\Modules\Payment\Contracts\GenericDTO;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Override;

class InvoiceRequestData implements GenericDTO {

	public function __construct(
		public string $currency_code,
		public int $company_id,
		public int $currency_id,
		public string $amount,
		public ?int $payment_request_id,
		public ?int $invoice_id,
		public ?string $invoice_number,
		public int $client_id
	){}

	/**
	 * toCents function
	 *
	 * @return integer
	 */
	private function toCents() : int {
		return General::toCents($this->amount);
	}

	#[Override]
	public function getData(): array
	{
		return [
			'currency_code'			=>	$this->currency_code,
			'currency_id'			=>	$this->currency_id,
			'company_id'			=>	$this->company_id,
			'amount'				=>	$this->amount,
			'amount_in_cents'		=>	$this->toCents(),
			'payment_request_id'	=>	$this->payment_request_id,
			'invoice_id'			=>	$this->invoice_id,
			'invoice_number'		=>	$this->invoice_number,
			'client_id'				=>	$this->client_id
		];
	}

}