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
	 * fetchFirst function
	 *
	 * @return Setting
	 */
	public function fetchFirst() : ?Setting {
		return $this->setting_repository->fetchFirst();
	}

	

}