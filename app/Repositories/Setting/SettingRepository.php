<?php

namespace App\Repositories\Setting;

use App\Models\Setting;

/**
 * SettingRepository class
 */
class SettingRepository{

	/**
	 * fetchUserLoginSettings function
	 *
	 * @param integer|null $company_id
	 * @param integer|null $user_id
	 * @return Setting
	 */
	public function fetchUserLoginSettings(?int $company_id, ?int $user_id) : Setting {

		$setting = null;

		if($company_id !== null && $user_id !== null){
			$setting = Setting::where([['company_id', '=', $company_id], ['user_id', '=', $user_id]])->first();
		}

		if(!$setting && $company_id !== null && $user_id === null){
			$setting = Setting::where('company_id', '=', $company_id)->first();
		}

		if(!$setting && $user_id !== null && $company_id === null){
			$setting = Setting::where('user_id', '=', $user_id)->first();
		}

		if(!$setting){
			$setting = Setting::where([['company_id', '=', null], ['user_id', '=', null]])->first();
		}

		return $setting;

	}

	/**
	 * fetchUserLoginSettingsForAdminArea function
	 *
	 * @param string $type
	 * @param integer $company_id
	 * @param integer $user_id
	 * @return Setting
	 */
	public function fetchUserLoginSettingsForAdminArea(string $type, int $company_id, int $user_id) : Setting {

		$global = Setting::where([['company_id', '=', null], ['user_id', '=', null]])->first();

		if($type === 'global'){
			return $global;
		}

		$setting = Setting::where([['company_id', '=', $company_id], ['user_id', '=', $user_id]])->first();

		if(!$setting){
			$setting = Setting::where('company_id', '=', $company_id)->first();
		}
		logger($setting);
		if(!$setting){
			$setting = $global;
		}

		return $setting;

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

		if($type === 'global'){

			$setting_default = Setting::where([['company_id', '=', null], ['user_id', '=', null]])->first();
			$setting_c = Setting::where([['company_id', '=', $company_id], ['user_id', '=', null]])->first();

			if(!$setting_c){
				$setting_c = new Setting();
				$setting_c->company_id = $company_id;
				$setting_c->user_id = null;
			}

			$setting_default->login_limits_flag = $settings['login_limits_flag'] ? 1 : 0;
			$setting_default->two_factor_auth_flag = $settings['two_factor_auth_flag'] ? 1 : 0;
			$setting_default->login_email_flag = $settings['login_email_flag'] ? 1 : 0;

			$setting_default->login_limits_attempts = $settings['login_limits_attempts'];
			$setting_default->login_limits_minutes = $settings['login_limits_minutes'];
			$setting_default->save();

		}else{

			$setting_c = Setting::where([['company_id', '=', $company_id], ['user_id', '=', $user_id]])->first();
			if(!$setting_c){
				$setting_c = new Setting();
				$setting_c->company_id = $company_id;
				$setting_c->user_id = $user_id;
			}

		}

		$setting_c->login_limits_flag = $settings['login_limits_flag'] ? 1 : 0;
		$setting_c->two_factor_auth_flag = $settings['two_factor_auth_flag'] ? 1 : 0;
		$setting_c->login_email_flag = $settings['login_email_flag'] ? 1 : 0;

		$setting_c->login_limits_attempts = $settings['login_limits_attempts'];
		$setting_c->login_limits_minutes = $settings['login_limits_minutes'];

		return $setting_c->save();

	}


}