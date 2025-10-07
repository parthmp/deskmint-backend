<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class CompanySettingsAdditionalFieldsControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	public function test_to_see_if_it_fails_to_save_additional_fields_for_company() : void {

		$device = 'device 123';
		$c = $this->set_access($device);

		Company::truncate();

		
		$company_id = $this->createTemporaryCompany();

	}

}
