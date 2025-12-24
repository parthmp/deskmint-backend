<?php

namespace App\Repositories\Company;

use App\Models\Company;

class CompanyRepository{

	/**
	 * fetchById function
	 *
	 * @return Company|null
	 */
	public function fetchDefault() : Company|null {
		return Company::where('default', '=', 1)->first();
	}

	/**
	 * fetchDefaultById function
	 *
	 * @param integer $company_id
	 * @return Company|null
	 */
	public function fetchDefaultById(int $company_id) : Company|null {
		return Company::where([['id', '=', $company_id], ['default', '=', 1]])->first();
	}

	/**
	 * create function
	 *
	 * @param string $company_name
	 * @param boolean $default
	 * @return integer
	 */
	public function create(string $company_name, bool $default) : int {

		$company = new Company();
		$company->company_name = $company_name;

		$default_flag = 0;

		if($default === true){
			$default_flag = 1;
		}

		$company->default = $default_flag;
		$company->save();

		return $company->id;

	}

}