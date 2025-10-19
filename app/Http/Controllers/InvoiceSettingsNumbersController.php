<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class InvoiceSettingsNumbersController extends Controller{
	
	use SettingsDefault;
	
	public function show(Request $request){

		try{

			$company_id = Sanitize::input($request->input('company_id'));

			$settings = SettingsSection::where([['type', '=', ISC_INVOICE_NUMBERS_TYPE], ['company_id', '=', $company_id]])->first();

			if($settings){
				return json_decode($settings->settings_json);
			}

			return $this->getDefaultInvoiceNumbersSettings();
					
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function saveOrUpdate(Request $request) : Response{

		$v = Validator::make($request->all(), [
			'number_padding'	=>	'required',
			'reset_counter'		=>	'required'
		]);
		
		if($v->fails()){
			return response(['message' => 'Please fill in required fields','validity' => 'invalid_data'], config('global.error_code'));
		}

		$company_id = Sanitize::input($request->input('company_id'));

		$number_padding = Sanitize::input($request->input('number_padding'));
		$reset_counter = Sanitize::input($request->input('reset_counter'));

		$number_padding_options = ['1', '0001', '00001', '000001', '0000001', '00000001'];
		$reset_counter_options = ['never', 'daily', 'weekly', 'two_weeks', 'monthly', 'two_months', 'three_months', 'four_months', 'six_months', 'yearly'];

		if(!in_array($number_padding, $number_padding_options)){
			return response(['message' => 'Invalid request','validity' => 'invalid_request_data'], config('global.error_code'));
		}

		if(!in_array($reset_counter, $reset_counter_options)){
			return response(['message' => 'Invalid request','validity' => 'invalid_request_data'], config('global.error_code'));
		}

		try{

			$number_pattern = '';
			if($request->filled('number_pattern')){
				$number_pattern = Sanitize::input($request->input('number_pattern'));
			}

			$json = json_encode([
						'number_padding' 	=> $number_padding,
						'reset_counter' 	=> $reset_counter,
						'number_pattern'	=>	$number_pattern
					]);

			$setting = SettingsSection::where([['type', '=', ISC_INVOICE_NUMBERS_TYPE], ['company_id', '=', $company_id]])->first();

			if($setting){
				$obj = $setting;
			}else{
				$obj = new SettingsSection();
				$obj->company_id = $company_id;
				$obj->type = ISC_INVOICE_NUMBERS_TYPE;
			}

			$obj->settings_json = $json;

			if($obj->save()){
				return response(['message' => 'Settings saved successfully', 'validity' => 'saved_success'], 200);
			}else{
				return General::wentWrong();
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function resetInvoiceNumber(Request $request){

		$json = json_encode([
			'reset'	=>	"1"
		]);
		
		$company_id = Sanitize::input($request->input('company_id'));


		try{

			$reset = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', ISC_INVOICE_NUMBER_RESET_TYPE]])->first();

			if(!$reset){
				$reset = new SettingsSection();
				$reset->company_id = $company_id;
				$reset->type = ISC_INVOICE_NUMBER_RESET_TYPE;
			}
			
			$reset->settings_json = $json;
			
			if($reset->save()){
				return response(['message' => 'Invoice number has been reset successfully', 'validity' => 'reset_success'], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

		
	}

}
