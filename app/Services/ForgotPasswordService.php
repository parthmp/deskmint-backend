<?php

	namespace App\Services;

	use App\Helpers\General;
	use App\Mail\SendResetPasswordEmail;
	use App\Models\CustomPasswordReset;
	use App\Models\User;
	use Illuminate\Support\Facades\Mail;

	class ForgotPasswordService{
		

		public function createResetCode($user, $device){
			
			$token = General::generateRandomString();

			$reset = new CustomPasswordReset();

			$reset->user_id = $user->id;
			$reset->reset_code = $token;
			$reset->device = $device;
			$reset->save();

			return $reset;

		}

		public function sendResetPasswordEmail($reset_token){

			Mail::to($reset_token->user->email)->queue(new SendResetPasswordEmail($reset_token));

		}

	}