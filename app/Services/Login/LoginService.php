<?php

namespace App\Services\Login;

use App\Helpers\Sanitize;
use App\Mail\SendLoginEmail;
use App\Mail\SendOTP;
use App\Models\TwoFactorAuthToken;
use App\Models\User;
use App\Repositories\AccessTokenData\AccessTokenDataRepository;
use App\Repositories\LoginAttempt\LoginAttemptRepository;
use App\Repositories\RefreshToken\RefreshTokenRepository;
use App\Repositories\TwoFactorAuthToken\TwoFactorAuthTokenRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * LoginService class
 */
class LoginService{

	public function __construct(
		private UserRepository $user_repository, 
		private TwoFactorAuthTokenRepository $two_factor_auth_token_repository, 
		private AccessTokenDataRepository $access_token_data_repository,
		private RefreshTokenRepository $refresh_token_repository,
		private LoginAttemptRepository $login_attempt_repository
	){}

	/**
	 * CheckLoginAuth function
	 *
	 * @param string $email
	 * @param string $password
	 * @return boolean
	 */
	public function CheckLoginAuth(string $email, string $password) : bool {
	
		$email = Sanitize::input($email);
		
		$user =	$this->user_repository->fetchByEmail($email);

		if($user){

			$db_password = $user->password;

			if(Hash::check($password, $db_password)){
				return true;
			}

		}

		return false;

	}

	/**
	 * sendOTPEmail function
	 *
	 * @param User $user
	 * @param string $otp
	 * @return void
	 */
	public function sendOTPEmail(User $user, string $otp) : void {
		Mail::to($user->email)->queue(new SendOTP($user, $otp));
	}
	
	/**
	 * sendLoginEmail function
	 *
	 * @param User $user
	 * @return void
	 */
	public function sendLoginEmail(User $user) : void {
		Mail::to($user->email)->queue(new SendLoginEmail($user));
	}

	/**
	 * generateOtpAndToken function
	 *
	 * @param User $user
	 * @param string $device
	 * @return array
	 */
	public function generateOtpAndToken(User $user, string $device) : array {

		$otp = rand(99999, 999999);
		$token = hash('sha512', uniqid($device));

		$this->two_factor_auth_token_repository->create($user->id, $token, $otp, $device);

		$this->sendOTPEmail($user, $otp);

		return ['token' => $token];

	}

	/**
	 * isTokenValid function
	 *
	 * @param TwoFactorAuthToken|null $found_token
	 * @param string $device
	 * @return boolean
	 */
	public function isTokenValid(?TwoFactorAuthToken $found_token, string $device) : bool {
		
		if(!$found_token){
			return false;
		}

		$diff = (now())->diffInSeconds($found_token->created_at, true);
		if($diff < config('global.otp_expiry')){
			return true;
		}

		return false;

	}

	/**
	 * invalidatePastTokens function
	 *
	 * @param User $user
	 * @param string $device
	 * @return void
	 */
	public function invalidatePastTokens(User $user, string $device) : void {
		
		$this->access_token_data_repository->deleteByUserIdAndDevice($user->id, $device);
		$this->refresh_token_repository->deleteByUserIdAndDevice($user->id, $device);
		
	}

	/**
	 * resetLoginAttempts function
	 *
	 * @param User $user
	 * @return void
	 */
	private function resetLoginAttempts(User $user) : void {
		$this->login_attempt_repository->deleteByUserId($user->id);
	}

	public function issueTokens($user, $device, $request){

		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		$this->access_token_data_repository->create($token_model->id, $user->id, $device, $request->header('User-Agent'), $request->ip());

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		$this->refresh_token_repository->create($user->id, $refresh_token_hash, $device);

		$this->resetLoginAttempts($user);

		return [
			'token'			=>	$access_token->plainTextToken,
			'refresh_token'	=>	$refresh_token_hash
		];

	}

}