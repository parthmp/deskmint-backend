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
	
	public function test_to_see_if_it_can_fetch_additional_fields_for_company_with_no_data_added() : void {

		$device = 'device 123';
		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id' 	=> $company_id
		]);
		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-additional-fields?' . $params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEmpty($json);

	}

	public function test_to_see_if_it_fails_to_save_additional_fields_for_company_with_invalid_data_1() : void {

		$device = 'device 123';
		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-additional-fields', [
			'company_id'		=>		$company_id
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_to_see_if_it_fails_to_save_additional_fields_for_company_with_invalid_data_2() : void {

		$device = 'device 123';
		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-additional-fields', [
			'company_id'		=>		$company_id,
			'all_fields'		=>		'bla'
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_to_see_if_it_fails_to_save_additional_fields_for_company_with_invalid_data_3() : void {

		$device = 'device 123';
		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-additional-fields', [
			'company_id'		=>		$company_id,
			'all_fields'		=>		[
				[
					
				]
			]
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_to_see_if_it_fails_to_save_additional_fields_for_company_with_invalid_data_4() : void {

		$device = 'device 123';
		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-additional-fields', [
			'company_id'		=>		$company_id,
			'all_fields'		=>		[
				[
					'label'		=>	'   ',
					'value'		=>	'some value'
				]
			]
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_to_see_if_it_saves_additional_fields_for_company_with_valid_data() : void {

		$device = 'device 123';
		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-additional-fields', [
			'company_id'		=>		$company_id,
			'all_fields'		=>		[
				[
					'label'		=>	'here is label with spaces    ',
					'value'		=>	'some value'
				],
				[
					'label'		=>	'here another label',
					'value'		=>	'more value'
				]
			]
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		/* now fetch */
		$params = http_build_query([
			'company_id' 	=> $company_id
		]);
		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-additional-fields?' . $params);

		$response->assertStatus(200);

		$json = $response->json();
		$this->assertEquals(2, count($json));

		/* 1 first because we get reversed data */
		$this->assertEquals('here is label with spaces', $json[1]['label']);
		$this->assertEquals('here another label', $json[0]['label']);

		$this->assertEquals('some value', $json[1]['value']);
		$this->assertEquals('more value', $json[0]['value']);

	}

}
