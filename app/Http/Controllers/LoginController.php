<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Turnstile;
use App\Http\Requests\Login\LoginRequest;
use App\Http\Requests\Login\ResendOTPRequest;
use App\Http\Requests\Login\ValidateOTPRequest;
use App\Services\Login\LoginService;
use App\Services\LoginAttempt\LoginAttemptService;
use App\Services\Setting\SettingService;
use App\Services\TwoFactorAuthToken\TwoFactorAuthTokenService;
use App\Services\User\UserService;
use Exception;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller{

	public function __construct(
		private UserService $user_service, 
		private LoginService $login_service, 
		private SettingService $setting_service, 
		private LoginAttemptService $login_attempt_service,
		private TwoFactorAuthTokenService $two_factor_auth_token_service
	){}

	public function login(LoginRequest $request){

		$data = $request->validated();

		$email = strtolower((string) $data['email_address']);
		
		$user =	$this->user_service->fetchUserByEmail($email);
		$setting = $this->setting_service->fetchFirst();

		if(!Turnstile::validate($data['turnstile_token'])){
			return response(['message' 	=> 'Invalid request','validity'	=> 'invalid_turnstile'], config('global.error_code'));
		}

		if(!$user){
			return response(['message'	=> 'Invalid email or password entered','validity'	=>	'invalid_email_and_password'], config('global.error_code'));
		}

		$this->login_attempt_service->resetAttempts($user, $setting);

		if($this->login_attempt_service->ifUserIsLockedOut($user, $setting)){
			return response(['message' 	=> 	'Locked out: Try again after '.$setting->login_limits_minutes.' minute(s) from your last login','validity'	=>	'locked_out'], config('global.error_code'));
		}

		if($this->login_service->CheckLoginAuth($email, $data['password'])){

			if($setting->two_factor_auth_flag == 1){
				
				$tfa = $this->login_service->generateOtpAndToken($user, $data['device']);
				return response(['tfa' => true,'token' =>	$tfa['token'], 'validity'	=>	'otp_sent', 'message' 	=> 	'OTP has been sent to the email'], 200);

			}else{
				
				$this->login_service->invalidatePastTokens($user, $data['device']);
				$tokens = $this->login_service->issueTokens($user, $data['device'], $request);

				if((int)$setting->login_email_flag === 1){
					$this->login_service->sendLoginEmail($user);
				}

				return response($tokens, 200);
				
			}

		}

		if($setting->login_limits_flag == 1){
			
			$left_attempts = $this->login_attempt_service->create($user);
			$left_attempts = ($setting->login_limits_attempts-$left_attempts);
			$res_message = 'You have '.$left_attempts.' attempt(s) left';
			if($left_attempts == 0){
				$res_message = 'You have been locked out for '.($setting->login_limits_minutes).' minute(s)';
			}
			return response(['message' 	=> 	$res_message,'validity'	=>	'locked_out_for_time'], config('global.error_code'));
		}else{

			return response(['message' 	=> 'Invalid email or password entered','validity'	=>	'invalid_email_password'], config('global.error_code'));

		}

	}
	

	public function resendOTP(ResendOTPRequest $request){

		$data = $request->validated();
		
		$found_token = $this->two_factor_auth_token_service->fetchUnusedByTokenAndDevice($data['token'], $data['device']);
		
		if(!$this->login_service->isTokenValid($found_token, $data['device'])){
			return response(['message' 	=> 	'Please login again','validity'	=>	'login_again'], 500);
		}

		$diff = (now())->diffInSeconds($found_token->created_at, true);

		if($diff < 60){
			return response(['message' => 'Please wait for one minute before requesting new OTP'], 401);
		}

		try{

			$user = $found_token->user;

			$found_token->delete();
			$tfa = $this->login_service->generateOtpAndToken($user, $data['device']);
			
			Log::info('Resend otp successful', ['user_id' => $user->id, 'device' => $data['device']]);

			return response(['tfa' => true,'token' => $tfa['token'],'message' => 'OTP has been sent to the email', 'validity' => 'otp_resent'], 200);		

		}catch(Exception $e){

			Log::info('Resend otp failed', ['user_id' => $user->id, 'device' => $data['device']]);

			return General::wentWrong();

		}
			
		

	}

	public function validateOTP(ValidateOTPRequest $request){

		$data = $request->validated();

		$found_token = $this->two_factor_auth_token_service->fetchOTPByTokenAndDevice($data['token'], $data['device'], $data['otp']);

		if(!$found_token){
			return response(['message' 	=> 'Invalid OTP entered', 'validity' => 'invalid_otp'], 401);
		}

		if(!$this->login_service->isTokenValid($found_token, $data['device'])){
			return response(['message' 	=> 'OTP expired, please login again', 'validity'	=>	'token_expired'], 500);
		}
		
		try{

			$this->two_factor_auth_token_service->markAsUsed($found_token);
			
			$this->login_service->invalidatePastTokens($found_token->user, $data['device']);
			$tokens = $this->login_service->issueTokens($found_token->user, $data['device'], $request);

			$setting = $this->setting_service->fetchFirst();
			
			if($setting->login_email_flag == 1){
				$this->login_service->sendLoginEmail($found_token->user);
			}

			return response($tokens, 200);

		}catch(Exception $e){
			
			return General::wentWrong();

		}

		

	}

}
