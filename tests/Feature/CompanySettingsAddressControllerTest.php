<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class CompanySettingsAddressControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	public function test_to_see_if_company_address_insert_or_updation_fails_with_invalid_data1() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-address', [
			'company_id'		=>		$company_id
		], $c['headers']);

		$json = $response->json();
		
		$response->assertStatus((int) config('global.error_code'));

		$this->assertEquals(6, count($json['errors']));

	}

	public function test_to_see_if_company_address_insert_or_updation_fails_with_invalid_data2() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-address', [
			'company_id'		=>		$company_id,
			'address_street'	=>		'',
			'apt'				=>		'    ',
			'city'				=>		'',

		], $c['headers']);

		$json = $response->json();
		
		$response->assertStatus((int) config('global.error_code'));

		$this->assertEquals(6, count($json['errors']));

	}

	public function test_to_see_if_company_address_insert_or_updation_fails_with_invalid_data3() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-address', [
			'company_id'		=>		$company_id,
			'address_street'	=>		'',
			'apt'				=>		'    ',
			'city'				=>		'',
			'state'				=>		'',
			'postal_code'		=>		'',
			'country_id'		=>		''

		], $c['headers']);

		$json = $response->json();
		
		$response->assertStatus((int) config('global.error_code'));

		$this->assertEquals(6, count($json['errors']));

	}

	public function test_to_see_if_company_address_insert_or_updation_fails_with_invalid_data4() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-address', [
			'company_id'		=>		$company_id,
			'address_street'	=>		'bla',
			'apt'				=>		'apt',
			'city'				=>		'city',
			'state'				=>		'state',
			'postal_code'		=>		'postal',
			'country_id'		=>		'500000'

		], $c['headers']);

		$json = $response->json();
		
		$response->assertStatus((int) config('global.error_code'));

		$this->assertEquals(1, count($json['errors']));

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

	// public function test_to_see_if_saving_company_address_works_with_partial_data_1() : void {

	// 	$device = 'device 123';
	// 	$c = $this->set_access($device);
	// 	$company_id = $this->createTemporaryCompany();

	// 	$response = $this->post('/api/manage-company-settings-address', [
	// 		'company_id'		=>		$company_id,
	// 		'address_street'	=>		'Elm Street',
	// 		'postal_code'		=>		'BLA 456',
	// 		'country_id'		=>		2
	// 	], $c['headers']);

	// 	$json = $response->json();

	// 	$response->assertStatus(200);
	// 	$this->assertArrayHasKey('validity', $json);
	// 	$this->assertEquals('saved_success', $json['validity']);

	// 	/* fetch to test */
	// 	$params = http_build_query([
	// 		'company_id' 		=> $company_id
	// 	]);
	// 	$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-address?' . $params);

	// 	$response->assertStatus(200);

	// 	$json = $response->json();

		
	// 	$this->assertNotEmpty($json['company']['company_name']);
	// 	$this->assertEquals('Elm Street', $json['company']['address_street']);
	// 	$this->assertEmpty($json['company']['apt']);
	// 	$this->assertEmpty($json['company']['city']);
	// 	$this->assertEmpty($json['company']['state']);
	// 	$this->assertEquals('BLA 456', $json['company']['postal_code']);
	// 	$this->assertNotNull($json['company']['country_id']);
	// 	$this->assertEquals(2, $json['company']['country_id']);

	// }

	// public function test_to_see_if_saving_company_address_works_with_partial_data_2() : void {

	// 	$device = 'device 123';
	// 	$c = $this->set_access($device);
	// 	$company_id = $this->createTemporaryCompany();

	// 	$response = $this->post('/api/manage-company-settings-address', [
	// 		'company_id'		=>		$company_id,
	// 		'apt'				=>		'apt 123',
	// 		'city'				=>		'Gotham City'
	// 	], $c['headers']);

	// 	$json = $response->json();

	// 	$response->assertStatus(200);
	// 	$this->assertArrayHasKey('validity', $json);
	// 	$this->assertEquals('saved_success', $json['validity']);

	// 	/* fetch to test */
	// 	$params = http_build_query([
	// 		'company_id' 		=> $company_id
	// 	]);
	// 	$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-address?' . $params);

	// 	$response->assertStatus(200);

	// 	$json = $response->json();

		
	// 	$this->assertNotEmpty($json['company']['company_name']);
	// 	$this->assertEmpty($json['company']['address_street']);
	// 	$this->assertEquals('apt 123', $json['company']['apt']);
	// 	$this->assertEquals('Gotham City', $json['company']['city']);
	// 	$this->assertEmpty($json['company']['state']);
	// 	$this->assertEmpty($json['company']['postal_code']);
	// 	$this->assertNull($json['company']['country_id']);

	// }

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

	public function test_to_see_if_saving_company_address_works_with_all_data_filled_with_peppol() : void {

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
			'country_id'		=>		20,
			'identifier'		=>		'ind',
			'scheme'			=>		'sch',
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
		$this->assertEquals('ind', $json['company']['address_identifier']);
		$this->assertEquals('sch', $json['company']['address_scheme']);
		

	}

	public function test_to_see_if_updating_company_address_works_with_all_data_filled_with_peppol() : void {

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
			'country_id'		=>		20,
			'identifier'		=>		'ind',
			'scheme'			=>		'sch',
		], $c['headers']);

		$json = $response->json();

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		//update
		$response = $this->post('/api/manage-company-settings-address', [
			'company_id'		=>		$company_id,
			'address_street'	=>		'Elm Street ov',
			'apt'				=>		'apt 123 ov',
			'city'				=>		'Gotham City ov',
			'state'				=>		'whatever state ov',
			'postal_code'		=>		'BLA 456 ov',
			'country_id'		=>		5,
			'identifier'		=>		'ind ov',
			'scheme'			=>		'sch ov',
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
		$this->assertEquals('Elm Street ov', $json['company']['address_street']);
		$this->assertEquals('apt 123 ov', $json['company']['apt']);
		$this->assertEquals('Gotham City ov', $json['company']['city']);
		$this->assertEquals('whatever state ov', $json['company']['state']);
		$this->assertEquals('BLA 456 ov', $json['company']['postal_code']);
		$this->assertEquals(5, (int) $json['company']['country_id']);
		$this->assertEquals('ind ov', $json['company']['address_identifier']);
		$this->assertEquals('sch ov', $json['company']['address_scheme']);
		

	}

}
