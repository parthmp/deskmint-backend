<?php

namespace App\Repositories\LoginAttempt;

use App\Models\LoginAttempt;

/**
 * SettingRepository class
 */
class LoginAttemptRepository{

	/**
	 * fetchByUserId function
	 *
	 * @param integer $user_id
	 * @return LoginAttempt|null
	 */
	public function fetchByUserId(int $user_id) : ?LoginAttempt {
		return LoginAttempt::where('user_id', '=', $user_id)->first();
	}

}