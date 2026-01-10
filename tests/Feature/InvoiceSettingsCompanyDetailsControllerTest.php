<?php

namespace Tests\Feature;

use App\Models\AdditionalCompanyField;
use App\Models\CustomFieldType;
use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceSettingsCompanyDetailsControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	public function test_if_it_fails_data_for_company_details_invoice_settings_with_invalid_data_1() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-company-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		
		$response = $response->json();
		$this->assertArrayHasKey('message', $response);
		$this->assertArrayHasKey('errors', $response);
		$this->assertEquals(1, count($response['errors']));

	}

	public function test_if_it_fails_data_for_company_details_invoice_settings_with_invalid_data_2() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-company-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'	=>	1,
													'text'	=>	'bla'
												]
											]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$response = $response->json();
		
		$this->assertArrayHasKey('message', $response);
		$this->assertArrayHasKey('errors', $response);
		$this->assertEquals(2, count($response['errors']));

	}

	public function test_if_it_fails_data_for_company_details_invoice_settings_with_invalid_data_3() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-company-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'		=>	1,
													'text'		=>	'bla',
													'value'		=>	'bla',
													'type'		=>	'normal',
													'mapped'	=>	['invalid_column']
												]
											]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('bad_request', $response['validity']);

	}

	public function test_if_it_saves_data_for_company_details_invoice_settings_with_for_normal_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-company-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'						=>	1,
													'text'						=>	'bla',
													'value'						=>	'bla',
													'type'						=>	'normal',
													'mapped'					=>	['company_name']
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		/* now check if it was saved */
		$settings = SettingsSection::where([['type', '=', ISC_INVOICE_COMPANY_DETAILS_TYPE], ['company_id', '=', $company_id]])->first();
		$settings = json_decode($settings->settings_json, true);
		
		$this->assertEquals([[
								'id'						=>	1,
								'text'						=>	'bla',
								'value'						=>	'bla',
								'type'						=>	'normal',
								'mapped'					=>	['company_name']
							]], $settings);
	}

	public function test_if_it_saves_data_for_company_details_invoice_settings_with_for_custom_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		/* add invoice custom field */
		AdditionalCompanyField::factory()->create([
			'id'			=>	100,
			'company_id'	=>	$company_id
		]);

		
		$response = $this->post('/api/manage-invoice-settings-company-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'											=>	1,
													'text'											=>	'bla',
													'value'											=>	'bla',
													'type'											=>	'custom',
													'id_column'										=>	100
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		/* now check if it was saved */
		$settings = SettingsSection::where([['type', '=', ISC_INVOICE_COMPANY_DETAILS_TYPE], ['company_id', '=', $company_id]])->first();
		$settings = json_decode($settings->settings_json, true);
		
		$this->assertEquals([[
								'id'						=>	'1',
								'text'						=>	'bla',
								'value'						=>	'bla',
								'type'						=>	'custom',
								'id_column'					=>	'100',
								'mapped'					=>	''
							]], $settings);
	}

	public function test_if_it_saves_data_for_company_details_invoice_settings_with_both_field_types() : void{
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		AdditionalCompanyField::factory()->create([
			'id'			=>	100,
			'company_id'	=>	$company_id
		]);

		AdditionalCompanyField::factory()->create([
			'id'			=>	101,
			'company_id'	=>	$company_id
		]);

		
		$response = $this->post('/api/manage-invoice-settings-company-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'											=>	1,
													'text'											=>	'bla',
													'value'											=>	'bla',
													'type'											=>	'normal',
													'mapped'										=>	['company_name', 'website']
												],
												[
													'id'											=>	2,
													'text'											=>	'bla',
													'value'											=>	'bla',
													'type'											=>	'custom',
													'mapped'										=>	'',
													'id_column'										=>	100
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-company-details?'. $params);
		$json = $response->json();
		
		$this->assertEquals(10, count($json['dropdown']));
		$this->assertEquals(2, count($json['rows']));
	}

}
