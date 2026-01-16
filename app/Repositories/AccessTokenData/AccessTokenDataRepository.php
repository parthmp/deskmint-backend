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

	/**
	 * deleteByUserIdAndDevice function
	 *
	 * @param integer $user_id
	 * @param string $device
	 * @return void
	 */
	public function deleteByUserIdAndDevice(int $user_id, string $device) : void {
		AccessTokenData::where([['user_id', '=', $user_id], ['device', '=', $device]])->delete();
	}

	/**
	 * create function
	 *
	 * @param integer $token_id
	 * @param integer $user_id
	 * @param string $device
	 * @param string $user_agent
	 * @param string $ip_address
	 * @return boolean
	 */
	public function create(int $token_id, int $user_id, string $device, string $user_agent, string $ip_address) : bool {

		$access_token_data = new AccessTokenData();
		$access_token_data->token_id = $token_id;
		$access_token_data->user_id = $user_id;
		$access_token_data->device = $device;
		$access_token_data->user_agent = $user_agent;
		$access_token_data->ip_address = $ip_address;
		return $access_token_data->save();

	}

}