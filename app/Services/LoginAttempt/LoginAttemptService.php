<?php

namespace App\Services\LoginAttempt;

use App\Models\LoginAttempt;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\LoginAttempt\LoginAttemptRepository;

/**
 * LoginAttemptService class
 */
class LoginAttemptService{

	/**
	 * __construct function
	 *
	 * @param LoginAttemptRepository $login_attempt_repository
	 */
	public function __construct(private LoginAttemptRepository $login_attempt_repository){}

	/**
	 * fetchByUserId function
	 *
	 * @param integer $user_id
	 * @return LoginAttempt|null
	 */
	public function fetchByUserId(int $user_id) : ?LoginAttempt {
		return $this->login_attempt_repository->fetchByUserId($user_id);
	}

	/**
	 * resetAttempts function
	 *
	 * @param User $user
	 * @param Setting $setting
	 * @return void
	 */
	public function resetAttempts(User $user, Setting $setting) : void {

		$attempt = $this->login_attempt_repository->fetchByUserId($user->id);

		if($attempt){

			$diff = (now())->diffInSeconds($attempt->last_attempted_at, true);
			
			if($diff > ($setting->login_limits_minutes*60)){

				$attempt->delete();

			}
		}
	}

	/**
	 * ifUserIsLockedOut function
	 *
	 * @param User $user
	 * @param Setting $setting
	 * @return boolean
	 */
	public function ifUserIsLockedOut(User $user, Setting $setting) : bool {

		if($setting->login_limits_flag == 0){
			return false;
		}

		$attempt = $this->fetchByUserId($user->id);
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

	/**
	 * create function
	 *
	 * @param User $user
	 * @return integer
	 */
	public static function create(User $user) : int {
		
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

		
		return (int) $number_of_attempts;

	}

}