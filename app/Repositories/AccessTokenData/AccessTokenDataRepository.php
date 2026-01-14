<?php

namespace App\Repositories\AccessTokenData;

use App\Models\AccessTokenData;

/**
 * AccessTokenDataRepository class
 */
class AccessTokenDataRepository{

	/**
	 * deleteById function
	 *
	 * @param integer $user_id
	 * @return void
	 */
	public function deleteById(int $user_id) : void {
		AccessTokenData::where('user_id', '=', $user_id)->delete();
	}

}