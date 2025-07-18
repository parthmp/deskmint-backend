<?php

namespace App\Services;

use App\Helpers\Sanitize;
use App\Mail\SendOTP;
use App\Models\AccessTokenData;
use App\Models\RefreshToken;
use App\Models\TwoFactorAuthToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class LoginService{

	public function CheckLoginAuth($email, $password){
	
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

	public function sendOTPEmail($user, $otp){
		Mail::to($user->email)->queue(new SendOTP($user, $otp));
	}

	public function generateOtpAndToken($user, $device){

		$otp = rand(99999, 999999);
		$token = hash('sha512', uniqid($device));

		$tfa = new TwoFactorAuthToken();
		$tfa->user_id = $user->id;
		$tfa->token = $token;
		$tfa->otp = $otp;
		$tfa->device = $device;
		$tfa->save();

		self::sendOTPEmail($user, $otp);

		return ['token' => $token];

	}

	public function isTokenValid($found_token, $device) {
		
		if(!$found_token){
			return false;
		}

		$diff = (now())->diffInSeconds($found_token->created_at, true);
		if($diff < config('global.otp_expiry')){
			return true;
		}

		return false;

	}

	public function invalidatePastTokens($user, $device){

		AccessTokenData::where([['user_id', '=', $user->id], ['device', '=', $device]])->delete();
		RefreshToken::where([['user_id', '=', $user->id], ['device', '=', $device]])->delete();

	}

	public function issueTokens($user, $device, $request){

		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		$access_token_data = new AccessTokenData();
		$access_token_data->token_id = $token_model->id;
		$access_token_data->user_id = $user->id;
		$access_token_data->device = $device;
		$access_token_data->user_agent = $request->header('User-Agent');
		$access_token_data->ip_address = $request->ip();
		$access_token_data->save();

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);
		$refresh_token = new RefreshToken();
		$refresh_token->user_id = $user->id;
		$refresh_token->refresh_token = $refresh_token_hash;
		$refresh_token->device = $device;
		$refresh_token->save();

		return [
			'token'			=>	$access_token->plainTextToken,
			'refresh_token'	=>	$refresh_token_hash
		];

	}

}