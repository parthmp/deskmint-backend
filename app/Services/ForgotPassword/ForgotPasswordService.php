<?php

	namespace App\Services;

	use App\Helpers\General;
	use App\Mail\SendResetPasswordEmail;
	use App\Models\AccessTokenData;
	use App\Models\CustomPasswordReset;
	use App\Models\RefreshToken;
	use App\Models\User;
	use Illuminate\Support\Facades\Hash;
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

		public function findResetCode($reset_code, $device){

			$reset_code_row = CustomPasswordReset::where([['reset_code', '=', $reset_code], ['device', '=', $device], ['used', '=', 0]])->first();

			if(!$reset_code_row){
				return false;
			}

			return $reset_code_row;

		}

		public function validateResetCode($reset_code_row){

			$seconds_limit = config('global.reset_code_expiry');

			$diff = (now())->diffInSeconds($reset_code_row->created_at, true);

			if($diff < ($seconds_limit)){
				return true;
			}

			return false;

		}

		public function updatePassword($reset_code_row, $password){

			$reset_code_row->used = 1;
			$reset_code_row->used_at = now();
			$reset_code_row->save();

			$user = $reset_code_row->user;

			$user->password = Hash::make($password);
			$user->update();

		}

		public function invalidateAllResetCodes($user, $device){

			CustomPasswordReset::where([['user_id', '=', $user->id], ['device', '=', $device]])->delete();

		}

		public function invalidatePastTokensForAllDevices($user){

			AccessTokenData::where('user_id', '=', $user->id)->delete();
			RefreshToken::where('user_id', '=', $user->id)->delete();

		}

	}