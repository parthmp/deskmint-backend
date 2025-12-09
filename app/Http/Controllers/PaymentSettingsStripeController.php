<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentSettingsStripeController extends Controller{
    
	use SettingsDefault;

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$stripe_settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_STRIPE_TYPE]])->first();

		if(!$stripe_settings){
			return $this->getDefaultStripeSettings();
		}

		$json = json_decode($stripe_settings->settings_json, true);
		
		try{
			$json['secret'] = decrypt($json['secret']);
		}catch(Exception $e){
			$json['secret'] = '';
		}
		
		return $json;

	}

	public function saveOrUpdate(Request $request){
		
		$company_id = Sanitize::input($request->input('company_id'));

		$v = Validator::make($request->all(), [
			'secret'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$secret = Sanitize::input($request->input('secret'));

		try{

			$secret = encrypt($secret);

			$stripe_settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_STRIPE_TYPE]])->first();

			if(!$stripe_settings){
				$stripe_settings = new SettingsSection();
				$stripe_settings->company_id = $company_id;
				$stripe_settings->type = PAYMENTS_STRIPE_TYPE;
			}

			$stripe_settings->settings_json = json_encode([
				'secret'	=>	$secret,
			]);

			if($stripe_settings->save()){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

		}catch(Exception $e){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

	}

	public function destroy(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_STRIPE_TYPE]])->delete();

		return response(['message' => 'Removed successfully', 'validity' => 'removed_success'], 200);

	}
    

}
