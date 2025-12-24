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

}