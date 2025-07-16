<?php

namespace App\Http\Controllers;

use App\Helpers\LoginHelper;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Helpers\Sanitize;
use App\Helpers\Turnstile;
use App\Mail\SendOTP;
use App\Models\LoginAttempt;
use App\Models\TwoFactorAuthToken;
use Illuminate\Support\Facades\Mail;
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

	private function generateOtpAndToken($user, $device){

		$otp = rand(99999, 999999);
		$token = hash('sha512', uniqid($device));

		$tfa = new TwoFactorAuthToken();
		$tfa->user_id = $user->id;
		$tfa->token = $token;
		$tfa->otp = $otp;
		$tfa->device = $device;
		$tfa->save();

		$this->sendOTPEmail($user, $otp);

		return ['token' => $token];

	}

	private function sendOTPEmail($user, $otp){
		Mail::to($user->email)->queue(new SendOTP($user, $otp));
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

					LoginHelper::reset($user, $setting);
					if(LoginHelper::ifUserIsLockedOut($user, $setting)){
						return response([
							'message' => 'Locked out: Try again after '.$setting->login_limits_minutes.' minute(s) from your last login'
						], env("ERROR_CODE"));
					}else{

						if($this->CheckLoginAuth($email, $password)){

							if($setting->two_factor_auth_flag == 1){
								
								/* proceed for 2fa */
								$tfa = $this->generateOtpAndToken($user, $device);
								
								return response([
									'tfa' => true,
									'token'	=>	$tfa['token'],
									'message' => 'OTP has been sent to the email'
								], 200);

							}else{
								/* issue tokens here */
								return response([
									'message' => 'login successful'
								], 200);
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

	private function isTokenValid($found_token, $device) {
		
		if(!$found_token){
			return false;
		}

		$diff = (now())->diffInSeconds($found_token->created_at, true);
		if($diff < env("OTP_EXPIRY_IN_SECONDS")){
			return true;
		}

		return false;

	}

	public function resendOTP(Request $request){

		$v = Validator::make($request->all(), [
			'token'		=>	'required',
			'device'	=> 	'required'
		]);

		if($v->fails()){
			return response([
				'message' => 'Invalid request'
			], env("ERROR_CODE"));
		}else{

			$token = Sanitize::input($request->input('token'));
			$device = Sanitize::input($request->input('device'));
			
			$found_token = TwoFactorAuthToken::where([['token', '=', $token], ['device', '=', $device], ['used', '=', 0]])->orderBy('id', 'desc')->first();

			if($this->isTokenValid($found_token, $device)){

				$diff = (now())->diffInSeconds($found_token->created_at, true);
				if($diff < 60){
					
					return response([
						'message' => 'Please wait for one minute before requesting new OTP'
					], 401);

				}else{

					/* send */
					$user = $found_token->user;

					$found_token->delete();
					$tfa = $this->generateOtpAndToken($user, $device);
								
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

				if($this->isTokenValid($found_token, $device)){
					/* log user in */
					return response([
						'message' => 'All good!'
					], 200);
				}else{
					return response([
						'message' => 'OTP expired, please login again'
					], 500);
				}

			}

		}

	}

}
