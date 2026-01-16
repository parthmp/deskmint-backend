<?php

namespace App\Repositories\TwoFactorAuthToken;

use App\Models\TwoFactorAuthToken;

/**
 * TwoFactorAuthTokenRepository class
 */
class TwoFactorAuthTokenRepository{

	/**
	 * fetchUnusedByTokenAndDevice function
	 *
	 * @param string $token
	 * @param string $device
	 * @return TwoFactorAuthToken|null
	 */
	public function fetchUnusedByTokenAndDevice(string $token, string $device) : ?TwoFactorAuthToken {
		return TwoFactorAuthToken::where([['token', '=', $token], ['device', '=', $device], ['used', '=', 0]])->orderBy('id', 'desc')->first();
	}

	/**
	 * fetchOTPByTokenAndDevice function
	 *
	 * @param string $token
	 * @param string $device
	 * @param string $otp
	 * @return TwoFactorAuthToken|null
	 */
	public function fetchOTPByTokenAndDevice(string $token, string $device, string $otp) : ?TwoFactorAuthToken {
		return TwoFactorAuthToken::where([['token', '=', $token], ['device', '=', $device], ['used', '=', 0], ['otp', '=', $otp]])->orderBy('id', 'desc')->first();
	}

	/**
	 * markAsUsed function
	 *
	 * @param TwoFactorAuthToken $two_factor_auth_obj
	 * @return boolean
	 */
	public function markAsUsed(TwoFactorAuthToken $two_factor_auth_obj) : bool {
		$two_factor_auth_obj->used = 1;
		return $two_factor_auth_obj->update();
	}

}