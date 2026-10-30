<?php

namespace App\Modules\Settings;

use App\Models\SettingsSection;

class DB {

	/**
	 * fetchAllSettings function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchAllSettings(int $company_id) : array {
		return SettingsSection::where('company_id', '=', $company_id)->get()->toArray();
	}

}