<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Turnstile;
use App\Http\Requests\ForgotPassword\ResetPasswordRequest;
use App\Http\Requests\ForgotPassword\SendResetPasswordCodeRequest;
use App\Services\ForgotPasswordService;
use App\Services\User\UserService;
use Exception;
use Illuminate\Support\Facades\Log;

class ForgotPasswordController extends Controller{

	public function __construct(private UserService $user_service, private ForgotPasswordService $forgot_password_service){}
    
	public function sendResetPasswordCode(SendResetPasswordCodeRequest $request){

		$data = $request->validated();

		if(!Turnstile::validate($data['turnstile_token'])){
			return response(['message' 	=> 	'Invalid request', 'validity'	=>	'invalid_turnstile'], config('global.error_code'));
		}
		
		$user = $this->user_service->fetchUserByEmail($data['email_address']);

		if($user){
			try{
				
				$reset_code_row = $this->forgot_password_service->createResetCode($user, $data['device']);
				$this->forgot_password_service->sendResetPasswordEmail($reset_code_row);

				Log::info('Send reset code successful', ['user_id' => $user->id, 'device' => $data['device']]);

			}catch(Exception $e){
				
				Log::info('Send reset code failed', ['user_id' => $user->id, 'device' => $data['device']]);
				
				return General::wentWrong();

			}
		}

		return response(['message' 	=> 	'Password reset email has been sent', 'validity' =>	'sent_reset_code'], 200);

	}

	public function resetPassword(ResetPasswordRequest $request){

		$data = $request->validated();

		if($data['password'] !== $data['retype_password']){
			return response(['message' 	=> 	'Password and retype password do not match', 'validity'	=>	'passwords_do_not_match'], config('global.error_code'));
		}

		$reset_code_row = $this->forgot_password_service->findResetCode($data['reset_code'], $data['device']);
		
		if(!$reset_code_row){
			return response(['message' 	=> 	'Invalid reset code entered', 'validity'	=>	'invalid_reset_code'], config('global.error_code'));
		}

		if(!$this->forgot_password_service->validateResetCode($reset_code_row)){
			return response(['message' 	=> 	'Reset code expired', 'validity'	=>	'reset_code_expired'], config('global.error_code'));
		}

		try{

			$this->forgot_password_service->updatePassword($reset_code_row, $data['password']);
			$this->forgot_password_service->invalidateAllResetCodes($reset_code_row->user, $data['device']);
			$this->forgot_password_service->invalidatePastTokensForAllDevices($reset_code_row->user);
			
			Log::info('Password reset successful', ['user_id' => $reset_code_row->user_id, 'device' => $data['device']]);

			return response(['message' 	=> 	'Password changed successfully', 'validity'	=>	'password_changed'], 200);

		}catch(Exception $e){

			Log::error('Password reset process failed', ['user_id' => $reset_code_row->user_id, 'error' => $e->getMessage()]);
			
			return General::wentWrong();

		}

		

	}

}
