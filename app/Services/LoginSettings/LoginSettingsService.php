<?php

namespace App\Services\LoginSettings;

use App\Models\Setting;
use App\Repositories\Setting\SettingRepository;
use Exception;
use Illuminate\Support\Facades\Auth;

/**
 * LoginSettingsService class
 */
class LoginSettingsService{

	public function __construct(
		private SettingRepository $setting_repository
	){}

	/**
	 * fetchLoginSettings function
	 *
	 * @param string $type
	 * @param integer $company_id
	 * @param integer|null $user_id
	 * @return Setting
	 */
	public function fetchLoginSettings(string $type, int $company_id, ?int $user_id = null) : Setting {

		if($type === 'global'){
			return $this->setting_repository->fetchUserLoginSettings($company_id, null);
		}
		
		if(!$user_id){
			throw new Exception("user_id is null");
		}

		return $this->setting_repository->fetchUserLoginSettings($company_id, (int) $user_id);

	}

	/**
	 * updateSettings function
	 *
	 * @param array $settings
	 * @param string $type
	 * @param integer|null $company_id
	 * @param integer|null $user_id
	 * @return boolean
	 */
	public function updateSettings(array $settings, string $type, ?int $company_id, ?int $user_id) : bool {
		return $this->setting_repository->updateSettings($settings, $type, $company_id, $user_id);
	}

}