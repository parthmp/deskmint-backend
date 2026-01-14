<?php

namespace App\Repositories\RefreshToken;

use App\Models\RefreshToken;

/**
 * RefreshTokenRepository class
 */
class RefreshTokenRepository{

	/**
	 * deleteById function
	 *
	 * @param integer $user_id
	 * @return void
	 */
	public function deleteById(int $user_id) : void {
		RefreshToken::where('user_id', '=', $user_id)->delete();
	}

}