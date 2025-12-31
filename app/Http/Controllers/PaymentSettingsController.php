<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Services\PaymentSettings\PaymentSettingsService;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller{

	public function __construct(private PaymentSettingsService $payment_settings_service){}
    
	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		return array_map("strtolower", $this->payment_settings_service->getGateWayNames((int) $company_id));
		
	}

}
