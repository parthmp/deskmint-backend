<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Helpers\Turnstile;
use App\Models\User;
use App\Services\ForgotPasswordService;
use Illuminate\Http\Request;
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

		}else{

			if(!Turnstile::validate($request->input('turnstile_token'))){
				return response(['message' 	=> 	'Invalid request', 'validity'	=>	'invalid_turnstile'], config('global.error_code'));
			}else{
				
				$email_address = Sanitize::input($request->input('email_address'));
				$device = Sanitize::input($request->input('device'));

				$user = User::where('email', '=', $email_address)->first();

				if($user){
					$reset_code_row = (new ForgotPasswordService)->createResetCode($user, $device);
					(new ForgotPasswordService)->sendResetPasswordEmail($reset_code_row);
				}

				return response(['message' 	=> 	'Password reset email has been sent', 'validity' =>	'sent_reset_code'], 200);


			}

		}

	}

}
