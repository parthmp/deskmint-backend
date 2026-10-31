<?php

namespace App\Modules\Payment\Gateways\Stripe\DTO;

use App\Enums\RecurringInvoices\Frequencies;
use App\Helpers\General;
use App\Modules\Payment\Contracts\GenericDTO;
use Override;

class UpdatePausedSubscription implements GenericDTO {

	public function __construct(
		private string $subscription_id,
		private string $amount,
		private string $currency,
		private int $frequency,
		private int $custom_frequency_days
	){}

	#[Override]
	public function getData(): array
	{

		$freqency_array = Frequencies::getForGatewayFromValue($this->frequency, $this->custom_frequency_days);


		return [
			'subscription_id'	=>	$this->subscription_id,
			'price'				=>	General::toCents($this->amount),
			'currency'			=>	$this->currency,
			'frequency'			=>	strtolower($freqency_array['interval_unit']),
			'interval'			=>	(int) $freqency_array['interval_count']
		];
	}
}