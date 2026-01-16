<?php

namespace App\Repositories\Setting;

use App\Models\Setting;

/**
 * SettingRepository class
 */
class SettingRepository{

	/**
	 * fetchFirst function
	 *
	 * @return Setting
	 */
	public function fetchFirst() : Setting {
		return Setting::first();
	}


}