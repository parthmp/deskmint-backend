<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericRequest;
use App\Services\PaymentSettings\PaymentSettingsService;

class PaymentSettingsController extends Controller{

	public function __construct(private PaymentSettingsService $payment_settings_service){}
    
	public function show(GenericRequest $request){

		$data = $request->validated();
		$company_id = $data['company_id'];
		return array_map("strtolower", $this->payment_settings_service->getGateWayNames((int) $company_id));
		
	}

}
