<?php

namespace App\Traits;

use App\Helpers\Sanitize;
use App\Models\SettingsSection;

trait PaymentGatewayDetails{

	/**
	 * getGateWayNames function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function getGateWayNames(int $company_id) : array {
		
		$company_id = Sanitize::input($company_id);

		$gateways = SettingsSection::select('type')->where('company_id', '=', $company_id)->where(function($query){
			$query->where('type', '=', PAYMENTS_PAYPAL_TYPE);
			$query->orwhere('type', '=', PAYMENTS_STRIPE_TYPE);
		})->get()->map(function($ele){
			return $ele->type === PAYMENTS_PAYPAL_TYPE ? 'PayPal' : 'Stripe';
		})->toArray();

		return $gateways;

	}

}