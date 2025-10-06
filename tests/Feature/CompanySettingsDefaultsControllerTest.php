<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\SetAccess;

class CompanySettingsDefaultsControllerTest extends TestCase{
	
	use RefreshDatabase, SetAccess;

	private function createTemporaryCompany() : int {
		
		$company = new Company();
		$company->company_name = 'Bla company';
		$company->default = 1;
		$company->save();
		
		return $company->id;

	}

	public function test_to_see_if_it_fetches_no_company_default_settings_data_without_having_any_added() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();

		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-defaults?'.$params);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertNull($json['invoice_terms']);
		$this->assertNull($json['invoice_footer']);

	}

	public function test_to_see_if_it_saves_company_default_settings_with_partial_data() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-defaults', [
			'invoice_terms' => 'sample',
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		/* fetch */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-defaults?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
	
		$this->assertEquals('sample', $json['invoice_terms']);
		$this->assertEmpty($json['invoice_footer']);

	}

	public function test_to_see_if_it_saves_company_default_settings_with_all_data() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-defaults', [
			'invoice_terms' 	=> 'sample terms text',
			'invoice_footer' 	=> 'sample footer text',
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		/* fetch */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-defaults?'.$params);

		$response->assertStatus(200);
		
		$json = $response->json();
	
		$this->assertEquals('sample terms text', $json['invoice_terms']);
		$this->assertEquals('sample footer text', $json['invoice_footer']);
		
	}

	public function test_to_see_if_it_overwrites_company_default_settings_with_all_data() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();
		
		$company_id = $this->createTemporaryCompany();

		$response = $this->post('/api/manage-company-settings-defaults', [
			'invoice_terms' 	=> 'sample terms text',
			'invoice_footer' 	=> 'sample footer text',
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		$response = $this->post('/api/manage-company-settings-defaults', [
			'invoice_terms' 	=> 'sample terms text overwritten',
			'invoice_footer' 	=> 'sample footer text overwritten',
			'company_id' 	=> $company_id
		], $c['headers']);

		/* fetch */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-defaults?'.$params);

		$response->assertStatus(200);
		
		$json = $response->json();
	
		$this->assertEquals('sample terms text overwritten', $json['invoice_terms']);
		$this->assertEquals('sample footer text overwritten', $json['invoice_footer']);
		
	}

}
