<?php

namespace App\Services\TwoFactorAuthToken;

use App\Models\TwoFactorAuthToken;
use App\Repositories\TwoFactorAuthToken\TwoFactorAuthTokenRepository;

/**
 * TwoFactorAuthTokenService class
 */
class TwoFactorAuthTokenService{

	/**
	 * __construct function
	 *
	 * @param TwoFactorAuthTokenRepository $two_factor_auth_token_repository
	 */
	public function __construct(private TwoFactorAuthTokenRepository $two_factor_auth_token_repository){}

	/**
	 * fetchUnusedByTokenAndDevice function
	 *
	 * @param string $token
	 * @param string $device
	 * @return TwoFactorAuthToken|null
	 */
	public function fetchUnusedByTokenAndDevice(string $token, string $device) : ?TwoFactorAuthToken {
		return $this->two_factor_auth_token_repository->fetchUnusedByTokenAndDevice($token, $device);
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
		return $this->two_factor_auth_token_repository->fetchOTPByTokenAndDevice($token, $device, $otp);
	}

	/**
	 * markAsUsed function
	 *
	 * @param TwoFactorAuthToken $two_factor_auth_obj
	 * @return boolean
	 */
	public function markAsUsed(TwoFactorAuthToken $two_factor_auth_obj) : bool {
		return $this->two_factor_auth_token_repository->markAsUsed($two_factor_auth_obj);
	}

}