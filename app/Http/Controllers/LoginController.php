<?php

namespace App\Http\Controllers;

use App\Helpers\LoginHelper;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Helpers\Sanitize;
use App\Helpers\Turnstile;
use App\Models\TwoFactorAuthToken;
use App\Services\LoginService;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller{

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
			], config('global.error_code'));
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
					], config('global.error_code'));

				}else{

					LoginHelper::reset($user, $setting);
					if(LoginHelper::ifUserIsLockedOut($user, $setting)){
						return response([
							'message' => 'Locked out: Try again after '.$setting->login_limits_minutes.' minute(s) from your last login'
						], config('global.error_code'));
					}else{

						if(app(LoginService::class)->CheckLoginAuth($email, $password)){

							if($setting->two_factor_auth_flag == 1){
								
								/* proceed for 2fa */
								$tfa = app(LoginService::class)->generateOtpAndToken($user, $device);
								
								return response([
									'tfa' => true,
									'token'	=>	$tfa['token'],
									'message' => 'OTP has been sent to the email'
								], 200);

							}else{
								
								app(LoginService::class)->invalidatePastTokens($user, $device);
								$tokens = app(LoginService::class)->issueTokens($user, $device, $request);

								return response($tokens, 200);
							}

						}else{

							if($setting->login_limits_flag == 1){
								
								$left_attempts = LoginHelper::addNew($user);
								$left_attempts = ($setting->login_limits_attempts-$left_attempts);
								$res_message = 'You have '.$left_attempts.' attempt(s) left';
								if($left_attempts == 0){
									$res_message = 'You have been locked out for '.($setting->login_limits_minutes).' minute(s)';
								}
								return response([
									'message' => $res_message
								], config('global.error_code'));
							}else{

								return response([
									'message' => 'Invalid email or password entered'
								], config('global.error_code'));

							}

						}

					}

				}


			}else{
				return response([
					'message' => 'Invalid request'
				], config('global.error_code'));
			}

		}	

	}
	

	public function resendOTP(Request $request){

		$v = Validator::make($request->all(), [
			'token'		=>	'required',
			'device'	=> 	'required'
		]);

		if($v->fails()){
			return response([
				'message' => 'Invalid request'
			], config('global.error_code'));
		}else{

			$token = Sanitize::input($request->input('token'));
			$device = Sanitize::input($request->input('device'));
			
			$found_token = TwoFactorAuthToken::where([['token', '=', $token], ['device', '=', $device], ['used', '=', 0]])->orderBy('id', 'desc')->first();

			if(app(LoginService::class)->isTokenValid($found_token, $device)){

				$diff = (now())->diffInSeconds($found_token->created_at, true);
				if($diff < 60){
					
					return response([
						'message' => 'Please wait for one minute before requesting new OTP'
					], 401);

				}else{

					/* send */
					$user = $found_token->user;

					$found_token->delete();
					$tfa = app(LoginService::class)->generateOtpAndToken($user, $device);
								
					return response([
						'tfa' => true,
						'token'	=>	$tfa['token'],
						'message' => 'OTP has been sent to the email'
					], 200);

				}


			}else{

				return response([
					'message' => 'Please login again'
				], 500);

			}

		}

	}

	public function validateOTP(Request $request){

		$v = Validator::make($request->all(), [
			'token'		=>	'required',
			'otp'		=>	'required',
			'device'	=> 	'required'
		]);

		if($v->fails()){

			return response([
				'message' => 'Invalid request'
			], 401);

		}else{

			$token = Sanitize::input($request->input('token'));
			$device = Sanitize::input($request->input('device'));
			$otp = Sanitize::input($request->input('otp'));

			$found_token = TwoFactorAuthToken::where([['token', '=', $token], ['device', '=', $device], ['used', '=', 0], ['otp', '=', $otp]])->orderBy('id', 'desc')->first();

			if(!$found_token){
				return response([
					'message' => 'Invalid OTP entered'
				], 401);
			}else{

				if(app(LoginService::class)->isTokenValid($found_token, $device)){

					/* log user in */
					$found_token->used = 1;
					$found_token->update();

					app(LoginService::class)->invalidatePastTokens($found_token->user, $device);
					$tokens = app(LoginService::class)->issueTokens($found_token->user, $device, $request);
					
					return response($tokens, 200);

				}else{

					return response([
						'message' => 'OTP expired, please login again'
					], 500);

				}

			}

		}

	}

}
