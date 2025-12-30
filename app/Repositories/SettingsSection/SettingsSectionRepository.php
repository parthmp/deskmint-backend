<?php

namespace App\Repositories\SettingsSection;

use App\Models\SettingsSection;
use Illuminate\Database\Eloquent\Collection;

class SettingsSectionRepository{

	/**
	 * fetchCompanyDetailsAndAddress function
	 *
	 * @param integer $company_id
	 * @return Collection|null
	 */
	public function fetchCompanyDetailsAndAddress(int $company_id) : Collection|null {
		return SettingsSection::where('company_id', $company_id)->where(function ($query){
			$query->where('type', ISC_INVOICE_COMPANY_ADDRESS_TYPE)->orWhere('type', ISC_INVOICE_COMPANY_DETAILS_TYPE);
		})->get();
	}

	/**
	 * fetchSettings function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @param boolean $settings_only
	 * @return SettingsSection|array|null
	 */
	public function fetchSettings(int $company_id, string $type, bool $settings_only = false) : SettingsSection|array|null {
		
		$row = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', $type]])->first();
		
		if(!$settings_only){
			return $row;
		}

		return json_decode($row->settings_json, true);

	}

	/**
	 * create function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @return SettingsSection
	 */
	public function createObj(int $company_id, string $type) : SettingsSection {

		$settings = new SettingsSection();
		$settings->company_id = $company_id;
		$settings->type = $type;

		return $settings;

	}

}