<?php

namespace Tests\Traits;

use App\Models\AccessTokenData;
use App\Models\Company;
use App\Models\RefreshToken;
use App\Models\User;

trait DefaultCompany{

	protected function set_default_company() : int{

		$company = Company::factory()->create([
			'company_name' 	=>  'ABC Company',
			'default'		=>	1
		]);

		return $company->id;

	}

	private function createTemporaryCompany() : int {
		$company = new Company();
		$company->company_name = 'Bla company';
		$company->default = 1;
		$company->save();
		return $company->id;
	}
}