<?php

	namespace App\Helpers;

	use App\Models\LoginAttempt;
	use App\Models\User;
	use App\Models\Setting;

	class LoginHelper{

		public static function ifUserIsLockedOut(User $user, Setting $setting){

			if($setting->login_limits_flag == 0){
				return false;
			}

			$attempt = LoginAttempt::where('user_id', '=', $user->id)->first();
			if(!$attempt){
				return false;
			}
			$diff = (now())->diffInSeconds($attempt->last_attempted_at, true);
			
			/* check if locked out here */
			if($attempt->number_of_attempts >= $setting->login_limits_attempts && $diff < ($setting->login_limits_minutes*60)){

				return true;

			}else{

				return false;

			}
			

		}

		public static function addNew(User $user){
			
			$past_attempt = LoginAttempt::where('user_id', '=', $user->id)->first();
			
			$number_of_attempts = 1;

			if($past_attempt){

				$number_of_attempts = ($past_attempt->number_of_attempts+1);
				$past_attempt->number_of_attempts = $number_of_attempts;
				$past_attempt->last_attempted_at = now();
				$past_attempt->save();

			}else{

				$attempt = new LoginAttempt();
				$attempt->user_id = $user->id;
				$attempt->number_of_attempts = $number_of_attempts;
				$attempt->last_attempted_at = now();
				$attempt->save();


			}

			
			return $number_of_attempts;

		}

		public static function reset(User $user, Setting $setting){

			$attempt = LoginAttempt::where('user_id', '=', $user->id)->first();

			if($attempt){

				$diff = (now())->diffInSeconds($attempt->last_attempted_at, true);
				
				if($diff > ($setting->login_limits_minutes*60)){

					$attempt->delete();

				}
			}
			
			

		}

	}