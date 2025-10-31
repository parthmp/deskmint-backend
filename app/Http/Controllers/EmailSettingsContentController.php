<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;

class EmailSettingsContentController extends Controller{

	use SettingsDefault;

	/**
	 * show function
	 *
	 * @param Request $request
	 * @return void
	 */
	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$email_content = SettingsSection::where([['type', '=', ESC_EMAIL_CONTENT_TYPE], ['company_id', '=', $company_id]])->first();

		if(!$email_content){
			return $this->getDefaultEmailContentSettings();
		}

		return json_decode($email_content->settings_json);

	}

	/**
	 * saveOrUpdate function
	 *
	 * @param Request $request
	 * @return void
	 */
	public function saveOrUpdate(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$email_content_invoice = Sanitize::input($request->input('email_content_invoice'));
		$email_content_reminder = Sanitize::input($request->input('email_content_reminder'));
		$payment_details = Sanitize::input($request->input('payment_details'));

		$email_content = SettingsSection::where([['type', '=', ESC_EMAIL_CONTENT_TYPE], ['company_id', '=', $company_id]])->first();

		try{

			if(!$email_content){
				$email_content = new SettingsSection();
				$email_content->company_id = $company_id;
				$email_content->type = ESC_EMAIL_CONTENT_TYPE;
			}
			
			$json_string = json_encode([
				'email_content_invoice'		=>	$email_content_invoice,
				'email_content_reminder'	=>	$email_content_reminder,
				'payment_details'			=>	$payment_details
			]);

			$email_content->settings_json = $json_string;

			if($email_content->save()){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
