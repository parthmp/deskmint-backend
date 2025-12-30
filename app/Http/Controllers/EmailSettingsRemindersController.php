<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\EmailSettingsReminders\CreateEmailSettingsRemindersRequest;
use App\Services\EmailSettingsReminders\EmailSettingsRemindersService;
use Exception;
use Illuminate\Http\Request;

class EmailSettingsRemindersController extends Controller{
    
	public function __construct(private EmailSettingsRemindersService $email_settings_reminders_service){
	}

	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{
			return $this->email_settings_reminders_service->fetch($company_id);
		}catch(Exception $e){
			return General::wentWrong();
		}

	}
	
	public function upsert(CreateEmailSettingsRemindersRequest $request){

		$data = $request->validated();

		$email_content = $this->email_settings_reminders_service->fetchRecord($data['company_id']);

		try{

			if($this->email_settings_reminders_service->updateByObj($data, $email_content)){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
