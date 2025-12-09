<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentSettingsPaypalController extends Controller{

	use SettingsDefault;

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$paypal_settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_PAYPAL_TYPE]])->first();

		if(!$paypal_settings){
			return $this->getDefaultPayPalSettings();
		}

		$json = json_decode($paypal_settings->settings_json, true);

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
			'client_id'	=>	'required',
			'secret'	=>	'required',
			'mode'		=>	'required|in:sandbox,live',
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$client_id = Sanitize::input($request->input('client_id'));
		$secret = Sanitize::input($request->input('secret'));
		$mode = Sanitize::input($request->input('mode'));

		try{

			$secret = encrypt($secret);

			$paypal_settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_PAYPAL_TYPE]])->first();

			if(!$paypal_settings){
				$paypal_settings = new SettingsSection();
				$paypal_settings->company_id = $company_id;
				$paypal_settings->type = PAYMENTS_PAYPAL_TYPE;
			}

			$paypal_settings->settings_json = json_encode([
				'client_id'	=>	$client_id,
				'secret'	=>	$secret,
				'mode'		=>	$mode,
			]);

			if($paypal_settings->save()){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

		}catch(Exception $e){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

	}

	public function destroy(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_PAYPAL_TYPE]])->delete();

		return response(['message' => 'Removed successfully', 'validity' => 'removed_success'], 200);

	}

}
