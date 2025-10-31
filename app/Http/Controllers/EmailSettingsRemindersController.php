<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;

class EmailSettingsRemindersController extends Controller{
    
	use SettingsDefault;

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$email_rem = SettingsSection::where([['type', '=', ESC_EMAIL_REMINDERS_TYPE], ['company_id', '=', $company_id]])->first();

		if(!$email_rem){
			return $this->getDefaultEmailRemindersSettings();
		}

		return json_decode($email_rem->settings_json);

	}
	
	public function saveOrUpdate(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$send_n_times = Sanitize::input($request->input('send_n_times'));
		$days_gap = Sanitize::input($request->input('days_gap'));
		
		$email_rem = SettingsSection::where([['type', '=', ESC_EMAIL_REMINDERS_TYPE], ['company_id', '=', $company_id]])->first();

		try{

			if(!$email_rem){
				$email_rem = new SettingsSection();
				$email_rem->company_id = $company_id;
				$email_rem->type = ESC_EMAIL_REMINDERS_TYPE;
			}
			
			$json_string = json_encode([
				'send_n_times'		=>	$send_n_times,
				'days_gap'			=>	$days_gap
			]);

			$email_rem->settings_json = $json_string;

			if($email_rem->save()){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
