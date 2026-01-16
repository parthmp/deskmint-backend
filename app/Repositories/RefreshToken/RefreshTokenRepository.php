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

	/**
	 * deleteByUserIdAndDevice function
	 *
	 * @param integer $user_id
	 * @param string $device
	 * @return void
	 */
	public function deleteByUserIdAndDevice(int $user_id, string $device) : void {
		RefreshToken::where([['user_id', '=', $user_id], ['device', '=', $device]])->delete();
	}

	/**
	 * create function
	 *
	 * @param integer $user_id
	 * @param string $refresh_token_hash
	 * @param string $device
	 * @return boolean
	 */
	public function create(int $user_id, string $refresh_token_hash, string $device) : bool {
		$refresh_token = new RefreshToken();
		$refresh_token->user_id = $user_id;
		$refresh_token->refresh_token = $refresh_token_hash;
		$refresh_token->device = $device;
		return $refresh_token->save();
	}

}