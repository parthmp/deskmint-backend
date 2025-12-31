<?php

namespace App\Repositories\InvoiceSettingsAPF;

use App\Models\AdditionalProductColumnsField;
use Illuminate\Database\Eloquent\Collection;

class InvoiceSettingsAPFRepository{

	/**
	 * fetchByCompanyId function
	 *
	 * @param integer $company_id
	 * @return Collection|null
	 */
	public function fetchByCompanyId(int $company_id) : Collection|null {

		return AdditionalProductColumnsField::where('company_id', '=', $company_id)->get();

	}

}