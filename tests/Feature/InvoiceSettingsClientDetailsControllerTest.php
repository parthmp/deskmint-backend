<?php

namespace Tests\Feature;

use App\Models\ClientCustomFieldValue;
use App\Models\ClientsCustomField;
use App\Models\CustomFieldType;
use App\Models\InvoicesCustomField;
use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceSettingsClientDetailsControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	private function getQuery($device, $queryParams, $url = '/api/manage-invoice-settings-client-details?'){

		$c = $this->set_access($device);

		$response = $this->withHeaders($c['headers'])->get($url . $queryParams);

		return $response;

	}

	public function test_if_it_fails_data_for_client_details_invoice_settings_with_invalid_data_1() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_it_fails_data_for_client_details_invoice_settings_with_invalid_data_2() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_it_fails_data_for_client_details_invoice_settings_with_invalid_data_3() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'	=>	1,
													'text'	=>	'bla'
												]
											]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_it_fails_data_for_client_details_invoice_settings_with_invalid_data_4() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'	=>	1,
													'text'	=>	'bla',
													'value'	=>	'bla',
													'type'	=>	'normal'
												]
											]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('bad_request', $response['validity']);

	}

	public function test_if_it_fails_data_for_client_details_invoice_settings_with_invalid_data_5() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-client-details', [
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

	public function test_if_it_fails_data_for_client_details_invoice_settings_with_invalid_data_6_for_custom_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'		=>	1,
													'text'		=>	'bla',
													'value'		=>	'bla',
													'type'		=>	'custom',
													'mapped'	=>	['invalid_column']
												]
											]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('bad_request', $response['validity']);

	}

	public function test_if_it_fails_data_for_client_details_invoice_settings_with_invalid_data_7_for_custom_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'						=>	1,
													'text'						=>	'bla',
													'value'						=>	'bla',
													'type'						=>	'custom',
													'clients_custom_field_id'	=>	100
												]
											]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('bad_request', $response['validity']);

	}

	public function test_if_it_saves_data_for_client_details_invoice_settings_with_for_normal_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'						=>	1,
													'text'						=>	'bla',
													'value'						=>	'bla',
													'type'						=>	'normal',
													'mapped'					=>	['first_name', 'last_name']
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		/* now check if it was saved */
		$settings = SettingsSection::where([['type', '=', ISC_INVOICE_CLIENT_DETAILS_TYPE], ['company_id', '=', $company_id]])->first();
		$settings = json_decode($settings->settings_json, true);
		
		$this->assertEquals([[
								'id'						=>	1,
								'text'						=>	'bla',
								'value'						=>	'bla',
								'type'						=>	'normal',
								'mapped'					=>	['first_name', 'last_name']
							]], $settings);
	}

	public function test_if_it_saves_data_for_client_details_invoice_settings_with_for_custom_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		/* add invoice custom field */
		ClientsCustomField::factory()->create([
			'id'	=>	100
		]);
		
		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'											=>	1,
													'text'											=>	'bla',
													'value'											=>	'bla',
													'type'											=>	'custom',
													'clients_custom_field_id'						=>	100
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		/* now check if it was saved */
		$settings = SettingsSection::where([['type', '=', ISC_INVOICE_CLIENT_DETAILS_TYPE], ['company_id', '=', $company_id]])->first();
		$settings = json_decode($settings->settings_json, true);
		
		$this->assertEquals([[
								'id'						=>	1,
								'text'						=>	'bla',
								'value'						=>	'bla',
								'type'						=>	'custom',
								'clients_custom_field_id'	=>	100
							]], $settings);
	}

	public function test_if_it_fetches_data_for_client_details_invoice_settings_with_both_field_types() : void{
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$this->setCustomFieldTypes();
		
		$fields = CustomFieldType::all();
		
		for($z = 0 ; $z < 3 ; $z++){
			
			/* add clients custom field */

			ClientsCustomField::factory()->create([
				'id'						=>	(100+$z),
				'label'						=>	'custom field here '.$z,
				'custom_field_type_id'		=>	$fields[$z]->id,
				'company_id'				=>	$company_id
			]);

		}
		
		$response = $this->post('/api/manage-invoice-settings-client-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'											=>	1,
													'text'											=>	'bla',
													'value'											=>	'bla',
													'type'											=>	'normal',
													'mapped'										=>	['first_name', 'last_name']
												],
												[
													'id'											=>	2,
													'text'											=>	'bla',
													'value'											=>	'bla',
													'type'											=>	'custom',
													'mapped'										=>	'',
													'clients_custom_field_id'						=>	100
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-client-details?'. $params);
		$json = $response->json();
		
		$this->assertEquals(12, count($json['dropdown']));
		$this->assertEquals(2, count($json['rows']));
	}

}
