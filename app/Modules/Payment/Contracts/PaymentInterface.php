<?php

namespace App\Modules\Payment\Contracts;

use Illuminate\Http\Request;

interface PaymentInterface {

	public function getInvoiceOrRequestData() : array;

	public function setContext(GenericDTO $dto) : self;
	
	public function getSubscriptionUrl() : string;

	public function handleWebhook(Request $request, array $settings_data) : bool;

}