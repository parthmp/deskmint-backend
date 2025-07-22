<?php

	namespace App\Services;

	use App\Helpers\Sanitize;
	use App\Models\Company;

	class CompanyService{

		public function addNewCompany(string $company_name, bool $default) : int{

			$company_name = Sanitize::input($company_name);

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