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

		if(!$setting && $user_id !== null && $company_id === null){
			$setting = Setting::where('user_id', '=', $user_id)->first();
		}

		if(!$setting && $company_id !== null && $user_id === null){
			$setting = Setting::where('company_id', '=', $company_id)->first();
		}

		if(!$setting){
			$setting = Setting::where([['company_id', '=', null], ['user_id', '=', null]])->first();
		}

		return $setting;

	}


}