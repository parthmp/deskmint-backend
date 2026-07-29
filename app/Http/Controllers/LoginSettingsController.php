<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\LoginSettings\LoginSettingsShowRequest;
use App\Http\Requests\LoginSettings\LoginSettingsUpdateRequest;
use App\Services\LoginSettings\LoginSettingsService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginSettingsController extends Controller {

	public function __construct(
		private LoginSettingsService $login_settings_service
	){}
    
	public function show(LoginSettingsShowRequest $request){

		$data = $request->validated();

		try{
			return $this->login_settings_service->fetchLoginSettings($data['type'], $data['company_id']);
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

	public function update(LoginSettingsUpdateRequest $request){
		
		$data = $request->validated();

		try{
			
			$update = [
				'login_email_flag'		=>	(bool) $data['login_email_flag'],
				'login_limits_flag'		=>	(bool) $data['login_limits_flag'],
				'two_factor_auth_flag'	=>	(bool) $data['two_factor_auth_flag'],
				'login_limits_attempts'	=>	(int) $data['login_limits_attempts'],
				'login_limits_minutes'	=>	(int) $data['login_limits_minutes'],
			];

			$this->login_settings_service->updateSettings($update, $data['type'], (int) $data['company_id'], (int) Auth::user()->id);

			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}
	}

}
