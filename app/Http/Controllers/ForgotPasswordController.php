<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Helpers\Turnstile;
use App\Models\CustomPasswordReset;
use App\Models\User;
use App\Services\ForgotPasswordService;
use App\Services\LoginService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller{
    
	public function sendResetPasswordCode(Request $request){

		$v = Validator::make($request->all(), [
			'email_address'		=>		'required|email',
			'turnstile_token'	=>		'required',
			'device'			=>		'required'
		]);

		if($v->fails()){
			return response(['message' 	=> 	'Invalid request', 'validity'	=>	'invalid_data'], config('global.error_code'));
		}

		if(!Turnstile::validate($request->input('turnstile_token'))){
			return response(['message' 	=> 	'Invalid request', 'validity'	=>	'invalid_turnstile'], config('global.error_code'));
		}
			
		$email_address = strtolower((string) Sanitize::input($request->input('email_address')));
		$device = Sanitize::input($request->input('device'));

		$user = User::where('email', '=', $email_address)->first();

		if($user){
			try{
				
				$reset_code_row = (new ForgotPasswordService)->createResetCode($user, $device);
				(new ForgotPasswordService)->sendResetPasswordEmail($reset_code_row);

				Log::info('Send reset code successful', ['user_id' => $user->id, 'device' => $device]);

			}catch(Exception $e){
				
				Log::info('Send reset code failed', ['user_id' => $user->id, 'device' => $device]);
				
				return General::wentWrong();

			}
		}

		return response(['message' 	=> 	'Password reset email has been sent', 'validity' =>	'sent_reset_code'], 200);

	}

	public function resetPassword(Request $request){

		$v = Validator::make($request->all(), [
			'reset_code'		=>		'required',
			'password'			=>		'required|min:8',
			'retype_password'	=>		'required|min:8',
			'device'			=>		'required'
		]);

		if($v->fails()){
			return response(['message' 	=> 	'Invalid request', 'validity'	=>	'invalid_data'], config('global.error_code'));
		}

		$reset_code = Sanitize::input($request->input('reset_code'));
		$device = Sanitize::input($request->input('device'));
		$password = $request->input('password');
		$retype_password = $request->input('retype_password');

		if($password !== $retype_password){
			return response(['message' 	=> 	'Password and retype password do not match', 'validity'	=>	'passwords_do_not_match'], config('global.error_code'));
		}

		$reset_code_row = (new ForgotPasswordService)->findResetCode($reset_code, $device);
		
		if(!$reset_code_row){
			return response(['message' 	=> 	'Invalid reset code entered', 'validity'	=>	'invalid_reset_code'], config('global.error_code'));
		}

		if(!(new ForgotPasswordService)->validateResetCode($reset_code_row)){
			return response(['message' 	=> 	'Reset code expired', 'validity'	=>	'reset_code_expired'], config('global.error_code'));
		}

		try{

			(new ForgotPasswordService)->updatePassword($reset_code_row, $password);
			(new ForgotPasswordService)->invalidateAllResetCodes($reset_code_row->user, $device);
			(new ForgotPasswordService)->invalidatePastTokensForAllDevices($reset_code_row->user);
			
			Log::info('Password reset successful', ['user_id' => $reset_code_row->user_id, 'device' => $device]);

			return response(['message' 	=> 	'Password changed successfully', 'validity'	=>	'password_changed'], 200);

		}catch(Exception $e){

			Log::error('Password reset process failed', ['user_id' => $reset_code_row->user_id, 'error' => $e->getMessage()]);
			
			return General::wentWrong();

		}

		

	}

}
