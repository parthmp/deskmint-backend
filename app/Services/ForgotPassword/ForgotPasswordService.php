<?php

namespace App\Services;

use App\Helpers\General;
use App\Mail\SendResetPasswordEmail;
use App\Models\CustomPasswordReset;
use App\Models\User;
use App\Repositories\AccessTokenData\AccessTokenDataRepository;
use App\Repositories\CustomPasswordReset\CustomPasswordResetRepository;
use App\Repositories\RefreshToken\RefreshTokenRepository;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordService{

	public function __construct(private CustomPasswordResetRepository $custom_password_reset_repository, private AccessTokenDataRepository $access_token_data_repository, private RefreshTokenRepository $refresh_token_repository){}
	
	/**
	 * createResetCode function
	 *
	 * @param User $user
	 * @param string $device
	 * @return CustomPasswordReset
	 */
	public function createResetCode(User $user, string $device) : CustomPasswordReset {
		
		$token = General::generateRandomString();

		return $this->custom_password_reset_repository->create($user, $device, $token);

	}

	/**
	 * sendResetPasswordEmail function
	 *
	 * @param CustomPasswordReset $reset_token
	 * @return void
	 */
	public function sendResetPasswordEmail(CustomPasswordReset $reset_token) : void {

		Mail::to($reset_token->user->email)->queue(new SendResetPasswordEmail($reset_token));

	}

	/**
	 * findResetCode function
	 *
	 * @param string $reset_code
	 * @param string $device
	 * @return CustomPasswordReset|null
	 */
	public function findResetCode(string $reset_code, string $device) : ?CustomPasswordReset {

		return $this->custom_password_reset_repository->fetchUnusedByCodeAndDevice($reset_code, $device);

	}

	/**
	 * validateResetCode function
	 *
	 * @param CustomPasswordReset $reset_code_row
	 * @return boolean
	 */
	public function validateResetCode(CustomPasswordReset $reset_code_row) : bool {

		$seconds_limit = config('global.reset_code_expiry');

		$diff = (now())->diffInSeconds($reset_code_row->created_at, true);

		if($diff < ($seconds_limit)){
			return true;
		}

		return false;

	}

	/**
	 * updatePassword function
	 *
	 * @param CustomPasswordReset $reset_code_row
	 * @param string $password
	 * @return boolean
	 */
	public function updatePassword(CustomPasswordReset $reset_code_row, string $password) : bool {

		return $this->updatePassword($reset_code_row, $password);

	}

	/**
	 * invalidateAllResetCodes function
	 *
	 * @param User $user
	 * @param string $device
	 * @return void
	 */
	public function invalidateAllResetCodes(User $user, string $device) : void {

		$this->custom_password_reset_repository->invalidateAllResetCodes($user->id, $device);

	}

	/**
	 * invalidatePastTokensForAllDevices function
	 *
	 * @param User $user
	 * @return void
	 */
	public function invalidatePastTokensForAllDevices(User $user) : void {

		$this->access_token_data_repository->deleteById($user->id);
		$this->refresh_token_repository->deleteById($user->id);

	}

}