<?php

namespace App\Services\Setting;

use App\Models\Setting;
use App\Repositories\Setting\SettingRepository;

/**
 * SettingService class
 */
class SettingService{

	/**
	 * __construct function
	 *
	 * @param SettingRepository $setting_repository
	 */
	public function __construct(private SettingRepository $setting_repository){}

	/**
	 * fetchUserLoginSettings function
	 *
	 * @param integer|null $company_id
	 * @param integer|null $user_id
	 * @return Setting|null
	 */
	public function fetchUserLoginSettings(?int $company_id, ?int $user_id) : ?Setting {
		return $this->setting_repository->fetchUserLoginSettings($company_id, $user_id);
	}

	

}