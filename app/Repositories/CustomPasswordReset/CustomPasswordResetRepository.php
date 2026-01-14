<?php

namespace App\Repositories\CustomPasswordReset;

use App\Models\CustomPasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * CustomPasswordResetRepository class
 */
class CustomPasswordResetRepository{
	
	/**
	 * create function
	 *
	 * @param User $user
	 * @param string $device
	 * @param string $token
	 * @return CustomPasswordReset
	 */
	public function create(User $user, string $device, string $token) : CustomPasswordReset {
		
		$reset = new CustomPasswordReset();
		$reset->user_id = $user->id;
		$reset->reset_code = $token;
		$reset->device = $device;
		$reset->save();

		return $reset;

	}

	/**
	 * fetchUnusedByCodeAndDevice function
	 *
	 * @param string $reset_code
	 * @param string $device
	 * @return CustomPasswordReset|null
	 */
	public function fetchUnusedByCodeAndDevice(string $reset_code, string $device) : ?CustomPasswordReset {

		return CustomPasswordReset::where([['reset_code', '=', $reset_code], ['device', '=', $device], ['used', '=', 0]])->first();

	}

	/**
	 * updatePasswordByObj function
	 *
	 * @param CustomPasswordReset $reset_code_row
	 * @param string $password
	 * @return boolean
	 */
	public function updatePasswordByObj(CustomPasswordReset $reset_code_row, string $password) : bool {

		$reset_code_row->used = 1;
		$reset_code_row->used_at = now();
		$reset_code_row->save();

		$user = $reset_code_row->user;

		$user->password = Hash::make($password);
		return $user->save();

	}

	/**
	 * invalidateAllResetCodes function
	 *
	 * @param integer $user_id
	 * @param string $device
	 * @return void
	 */
	public function invalidateAllResetCodes(int $user_id, string $device) : void {

		CustomPasswordReset::where([['user_id', '=', $user_id], ['device', '=', $device]])->delete();

	}

}