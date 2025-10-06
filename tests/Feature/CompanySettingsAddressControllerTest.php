<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\SetAccess;

class CompanySettingsAddressControllerTest extends TestCase{

	 use RefreshDatabase, SetAccess;

	private function createTemporaryCompany() : int {
		$company = new Company();
		$company->company_name = 'Bla company';
		$company->default = 1;
		$company->save();
		return $company->id;
	}

	public function test_to_see_if_it_fetches_no_company_address_data_successfully_without_adding_data() : void {
		
		$device = 'device 123';
		$c = $this->set_access($device);

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-address?' . $params);

		$response->assertStatus(200);

		$json = $response->json();

		
		$this->assertNotEmpty($json['company']['company_name']);
		$this->assertEmpty($json['company']['address_street']);
		$this->assertEmpty($json['company']['apt']);
		$this->assertEmpty($json['company']['city']);
		$this->assertEmpty($json['company']['state']);
		$this->assertEmpty($json['company']['postal_code']);
		$this->assertNull($json['company']['country_id']);
		

	}

	public function test_to_see_if_saving_company_address_works_with_partial_data_1() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-address', [
			'company_id'		=>		$company_id,
			'address_street'	=>		'Elm Street',
			'postal_code'		=>		'BLA 456',
			'country_id'		=>		2
		], $c['headers']);

		$json = $response->json();

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		/* fetch to test */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);
		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-address?' . $params);

		$response->assertStatus(200);

		$json = $response->json();

		
		$this->assertNotEmpty($json['company']['company_name']);
		$this->assertEquals('Elm Street', $json['company']['address_street']);
		$this->assertEmpty($json['company']['apt']);
		$this->assertEmpty($json['company']['city']);
		$this->assertEmpty($json['company']['state']);
		$this->assertEquals('BLA 456', $json['company']['postal_code']);
		$this->assertNotNull($json['company']['country_id']);
		$this->assertEquals(2, $json['company']['country_id']);

	}

	public function test_to_see_if_saving_company_address_works_with_partial_data_2() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-address', [
			'company_id'		=>		$company_id,
			'apt'				=>		'apt 123',
			'city'				=>		'Gotham City'
		], $c['headers']);

		$json = $response->json();

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		/* fetch to test */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);
		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-address?' . $params);

		$response->assertStatus(200);

		$json = $response->json();

		
		$this->assertNotEmpty($json['company']['company_name']);
		$this->assertEmpty($json['company']['address_street']);
		$this->assertEquals('apt 123', $json['company']['apt']);
		$this->assertEquals('Gotham City', $json['company']['city']);
		$this->assertEmpty($json['company']['state']);
		$this->assertEmpty($json['company']['postal_code']);
		$this->assertNull($json['company']['country_id']);

	}

	public function test_to_see_if_saving_company_address_works_with_all_data_filled() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-address', [
			'company_id'		=>		$company_id,
			'address_street'	=>		'Elm Street',
			'apt'				=>		'apt 123',
			'city'				=>		'Gotham City',
			'state'				=>		'whatever state',
			'postal_code'		=>		'BLA 456',
			'country_id'		=>		20
		], $c['headers']);

		$json = $response->json();

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		/* fetch to test */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);
		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-address?' . $params);

		$response->assertStatus(200);

		$json = $response->json();

		
		$this->assertEquals('Bla company', $json['company']['company_name']);
		$this->assertEquals('Elm Street', $json['company']['address_street']);
		$this->assertEquals('apt 123', $json['company']['apt']);
		$this->assertEquals('Gotham City', $json['company']['city']);
		$this->assertEquals('whatever state', $json['company']['state']);
		$this->assertEquals('BLA 456', $json['company']['postal_code']);
		$this->assertEquals(20, $json['company']['country_id']);
		

	}

}
