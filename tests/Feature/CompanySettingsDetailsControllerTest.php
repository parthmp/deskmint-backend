<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class CompanySettingsDetailsControllerTest extends TestCase{

    use RefreshDatabase, SetAccess, DefaultCompany;

	private function getQuery($device, $params, $url = '/api/manage-company-settings-details?'){

		$c = $this->set_access($device);

		$response = $this->withHeaders($c['headers'])->get($url . $params);

		return $response;

	}

	public function test_to_see_if_it_fetches_no_company_details_data_successfully_without_adding_data() : void {
		
		$device = 'device 123';

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->getQuery($device, $params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertNotEmpty($json['company_name']);
		$this->assertEmpty($json['size']);
		$this->assertEmpty($json['id_number']);
		$this->assertEmpty($json['gst_vat_number']);
		$this->assertEmpty($json['classification']);
		$this->assertEmpty($json['website']);
		$this->assertEmpty($json['email']);
		$this->assertEmpty($json['phone']);

	}

	public function test_to_see_if_saving_company_details_fails_invalid_data_1() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-details', [
			'company_id'	=>		$company_id
		], $c['headers']);

		$json = $response->json();
		
		$response->assertStatus((int) config('global.error_code'));
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('company_name', $json['errors']);
		$this->assertEquals(1, (int) count($json['errors']['company_name']));

	}
	
	public function test_to_see_if_saving_company_details_fails_invalid_data_2() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-details', [
			'company_id'	=>		$company_id,
			'company_name'	=>		'   '
		], $c['headers']);

		$json = $response->json();
		
		$response->assertStatus((int) config('global.error_code'));
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('company_name', $json['errors']);
		$this->assertEquals(1, (int) count($json['errors']['company_name']));
	}

	public function test_to_see_if_it_saves_company_details_with_only_required_fields() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-details', [
			'company_id'	=>		$company_id,
			'company_name'	=>		'something else here   '
		], $c['headers']);

		$json = $response->json();
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		$company = Company::where('id', '=', $company_id)->first()->toArray();
		
		$this->assertNotEmpty($company['company_name']);
		$this->assertEquals('something else here', $company['company_name']);

		$this->assertEmpty($company['size']);
		$this->assertEmpty($company['id_number']);
		$this->assertEmpty($company['gst_vat_number']);
		$this->assertEmpty($company['classification']);
		$this->assertEmpty($company['website']);
		$this->assertEmpty($company['email']);
		$this->assertEmpty($company['phone']);

	}

	public function test_to_see_if_it_saves_company_details_with_all_fields_filled() : void {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-details', [
			'company_id'		=>		$company_id,
			'company_name'		=>		'something else here new  ',
			'size'				=>		'1-20',
			'id_number'			=>		'123',
			'gst'				=>		'GST456',
			'classification'	=>		'CLS789',
			'website'			=>		'https://bla.com',
			'email'				=>		'what@ever.com',
			'phone'				=>		'+123469870'
		], $c['headers']);

		$json = $response->json();
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);
		
		/* now fetch to check the data */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-details?'.$params);
		$response->assertStatus(200);

		$json = $response->json();

		$this->assertNotEmpty($json['company_name']);
		$this->assertEquals('something else here new', $json['company_name']);
		$this->assertEquals('1-20', $json['size']);
		$this->assertEquals('123', $json['id_number']);
		$this->assertEquals('GST456', $json['gst_vat_number']);
		$this->assertEquals('CLS789', $json['classification']);
		$this->assertEquals('https://bla.com', $json['website']);
		$this->assertEquals('what@ever.com', $json['email']);
		$this->assertEquals('+123469870', $json['phone']);

		

	}

}
