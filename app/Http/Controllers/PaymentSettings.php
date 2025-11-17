<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use Illuminate\Http\Request;

class PaymentSettings extends Controller{
    
	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$gateways = SettingsSection::select('type')->where('company_id', '=', $company_id)->where(function($query) use ($company_id){
			$query->where('type', '=', PAYMENTS_PAYPAL_TYPE);
			$query->orwhere('type', '=', PAYMENTS_STRIPE_TYPE);
		})->get()->map(function($ele){
			return $ele->type === PAYMENTS_PAYPAL_TYPE ? 'paypal' : 'stripe';
		});

		return $gateways;

	}

}
