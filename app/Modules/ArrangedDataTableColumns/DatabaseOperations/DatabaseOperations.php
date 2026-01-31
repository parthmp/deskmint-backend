<?php

namespace App\Modules\ArrangedDataTableColumns\DatabaseOperations;

use App\Models\SettingsIndexColumn;
use App\Models\UserIndexColumn;
use Illuminate\Support\Facades\Auth;

/**
 * DatabaseOperations class
 */
class DatabaseOperations{

	private string $model;

	/**
	 * setModel function
	 *
	 * @param string $model
	 * @return self
	 */
	public function setModel(string $model) : self {

		$this->model = $model;
		return $this;

	}

	/**
	 * pluckIdsByCompanyIdC function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function pluckIdsByCompanyIdC(int $company_id) : array {
		return $this->model::where('company_id', '=', $company_id)->pluck('id')->toArray();
	}

	/**
	 * fetchUserIndexColumnDataByUserId function
	 *
	 * @param integer $company_id
	 * @param string $feature_name
	 * @return UserIndexColumn|null
	 */
	public function fetchUserIndexColumnDataByUserId(int $company_id, string $feature_name) : ?UserIndexColumn {
		return UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->first();
	}

	/**
	 * fetchSettingsIndexColumnDataByFeatureName function
	 *
	 * @param integer $company_id
	 * @param string $feature_name
	 * @return SettingsIndexColumn|null
	 */
	public function fetchSettingsIndexColumnDataByFeatureName(int $company_id, string $feature_name) : ?SettingsIndexColumn {
		return SettingsIndexColumn::where([['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->first();
	}

	/**
	 * fetchGeneralCustomColumns function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchGeneralCustomColumns(int $company_id) : array {
		return $this->model::where('company_id', '=', $company_id)->whereHas('customFieldType')->with('customFieldType')->get()->toArray();
	}



}