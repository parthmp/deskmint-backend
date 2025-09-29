<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceSettingsClientDetailsController extends Controller{

	use SettingsDefault;

	public function show(Request $request) : mixed{

		try{

			$company_id = Sanitize::input($request->input('company_id'));

			$settings = SettingsSection::where([['type', '=', 'invoice_client_details'], ['company_id', '=', $company_id]])->first();

			if($settings){
				return json_decode($settings->settings_json);
			}

			return $this->getDefaultInvoiceClientDetailsSettings();
					
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	private function validateClientSettingsPost(array $rows) : bool{

		$valid = true;

		$default = $this->getDefaultInvoiceClientDetailsSettings();
		$default = array_merge($default['dropdown'], $default['rows']);

		foreach($rows as $row){
			/*
				{
					"id": 8,
					"text": "Email",
					"value": "email",
					"mapped": [
						"email"
					],
					"type": "normal"
				}
			*/
			if($row['type'] === 'normal'){

			}else{
				
			}

		}

		return $valid;
	}

	public function saveOrUpdate(Request $request){
		
		$v = Validator::make($request->all(), [
			'rows'              => 'required|array',
			'rows.*.id'         => 'required|integer',
			'rows.*.text'       => 'required|string',
			'rows.*.value'      => 'required|string',
			'rows.*.type'       => 'required|string|in:normal,custom'
		]);

		if($v->fails()){
			return response(['message' => json_encode($v->errors()),'validity' => 'invalid_data'], config('global.error_code'));
		}

		/* now validate before moving forward */


		$company_id = Sanitize::input($request->input('company_id'));

		$settings = SettingsSection::where([['type', '=', 'invoice_client_details'], ['company_id', '=', $company_id]])->first();

		if($settings){
			$s = $settings;
		}else{
			$s = new SettingsSection();
		}



	}

}
