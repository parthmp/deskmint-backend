<?php

namespace App\Http\Controllers;

use App\Helpers\LoginAttempts;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Helpers\Sanitize;
use App\Helpers\Turnstile;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{	

	private function CheckLoginAuth($email, $password){
		
		$email = Sanitize::input($email);
		
		$user = User::where('email', '=', $email)->first();

		if($user){

			$db_password = $user->password;

			if(Hash::check($password, $db_password)){
				return true;
			}

		}

		return false;

	}

	public function login(Request $request){
		
		$v = Validator::make($request->all(), [
			'email_address' 		=> 'required|email',
			'password' 				=> 'required',
			'turnstile_token' 		=> 'required',
			'device' 				=> 'required'
		]);

		if($v->fails()){
			return response([
				'message' => 'Please enter email and password.'
			], env("ERROR_CODE"));
		}else{
			
			if(Turnstile::validate($request->input('turnstile_token'))){
				
				$email = Sanitize::input($request->input('email_address'));
				$device = Sanitize::input($request->input('device'));
				$password = $request->input('password');
				
				$user = User::where('email', '=', $email)->first();
				$setting = Setting::first();
				if(!$user){

					return response([
						'message' => 'Invalid email or password entered'
					], env("ERROR_CODE"));

				}else{

					LoginAttempts::reset($user, $setting);
					if(LoginAttempts::ifUserIsLockedOut($user, $setting)){
						return response([
							'message' => 'Locked out: Try again after '.$setting->login_limits_minutes.' minute(s) from your last login'
						], env("ERROR_CODE"));
					}else{

						if($this->CheckLoginAuth($email, $password)){

							if($setting->two_factor_auth_flag == 1){
								/* proceed for 2fa */
								return response([
									'message' => 'proceecing to 2fa'
								], 200);
							}else{
								/* issue tokens here */
								return response([
									'message' => 'login successful'
								], 200);
							}

						}else{

							if($setting->login_limits_flag == 1){
								
								$left_attempts = LoginAttempts::addNew($user);
								return response([
									'message' => 'You have '.($setting->login_limits_attempts-$left_attempts).' attempt(s) left'
								], env("ERROR_CODE"));
							}else{

								return response([
									'message' => 'Invalid email or password entered'
								], env("ERROR_CODE"));

							}

						}

					}

				}


			}else{
				return response([
					'message' => 'Invalid request'
				], env("ERROR_CODE"));
			}

		}

		

	}

}
