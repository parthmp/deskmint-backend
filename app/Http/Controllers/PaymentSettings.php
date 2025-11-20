<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Traits\PaymentGatewayDetails;
use Illuminate\Http\Request;

class PaymentSettings extends Controller{

	use PaymentGatewayDetails;
    
	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		return array_map("strtolower", $this->getGateWayNames((int) $company_id));
		
	}

}
