<?php

namespace Tests\Feature;

use App\Models\AdditionalProductColumnsField;
use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceSettingsAPFControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	public function test_if_it_fetches_additional_product_fields_successfully_empty(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-additional-product-fields?'. $params);
		
		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertEmpty($json['labels']);
		$this->assertEmpty($json['types']);
		$this->assertEmpty($json['taxes']);
		
		

	}

	public function test_if_it_fetches_additional_product_fields_successfully_not_empty(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		AdditionalProductColumnsField::factory()->count(5)->create([
			'company_id'	=>		$company_id
		]);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-additional-product-fields?'. $params);
		
		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertNotEmpty($json['labels']);
		$this->assertNotEmpty($json['types']);
		$this->assertNotEmpty($json['taxes']);

		$this->assertEquals(5, count($json['labels']));
		$this->assertEquals(5, count($json['types']));
		$this->assertEquals(5, count($json['taxes']));
		
	}

	public function test_if_fails_to_save_additional_product_fields_with_invalid_data_provided_1(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(3, count($json['errors']));

	}

	public function test_if_fails_to_save_additional_product_fields_with_invalid_data_provided_2(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[],
			'types'					=>		'invalid',
			'taxes'					=>		[],
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(3, count($json['errors']));

	}

	public function test_if_fails_to_save_additional_product_fields_with_invalid_data_provided_3(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'value'	=>	'   '
												]
											],
			'types'					=>		[],
			'taxes'					=>		[],
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(3, count($json['errors']));

	}

	public function test_if_fails_to_save_additional_product_fields_with_invalid_values_provided_4(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'  '
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'  '
												]
			],
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(2, count($json['errors']));

	}

	public function test_if_fails_to_save_additional_product_fields_with_invalid_ids_provided_5(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												]
											],
			'types'					=>		[
												[
													'id'	=>	2,
													'value'	=>	'normal'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	3,
													'value'	=>	'0.25'
												]
			],
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}

	public function test_if_it_saves_additional_product_fields_with_valid_data_provided(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'normal'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0.25'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-additional-product-fields?'. $params);
		
		$json = $response->json();
		
		$this->assertEquals('abc123', $json['labels'][0]['value']);
		$this->assertEquals('normal', $json['types'][0]['value']);
		$this->assertEquals(0.25, (float)$json['taxes'][0]['value']);

	}

	public function test_if_it_saves_additional_product_fields_with_valid__multiple_entries_provided(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												],
												[
													'id'	=>	2,
													'value'	=>	'something'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'normal'
												],
												[
													'id'	=>	2,
													'value'	=>	'tax'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0'
												],
												[
													'id'	=>	2,
													'value'	=>	'5.59'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-additional-product-fields?'. $params);
		
		$json = $response->json();
		
		$this->assertEquals('abc123', $json['labels'][0]['value']);
		$this->assertEquals('normal', $json['types'][0]['value']);
		$this->assertEquals(0, (float)$json['taxes'][0]['value']);

		$this->assertEquals('something', $json['labels'][1]['value']);
		$this->assertEquals('tax', $json['types'][1]['value']);
		$this->assertEquals(5.59, (float)$json['taxes'][1]['value']);

	}

	public function test_if_it_overwrites_additional_product_fields_with_valid__multiple_entries_provided(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												],
												[
													'id'	=>	2,
													'value'	=>	'something'
												],
												[
													'id'	=>	3,
													'value'	=>	'something else'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'normal'
												],
												[
													'id'	=>	2,
													'value'	=>	'tax'
												],
												[
													'id'	=>	3,
													'value'	=>	'tax'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0'
												],
												[
													'id'	=>	2,
													'value'	=>	'5.59'
												],
												[
													'id'	=>	3,
													'value'	=>	'15.85'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	3,
													'value'	=>	'something else overwritten'
												]
											],
			'types'					=>		[
												[
													'id'	=>	3,
													'value'	=>	'tax'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	3,
													'value'	=>	'150.85'
												]
											]
		], $c['headers']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-additional-product-fields?'. $params);
		
		$json = $response->json();

		$this->assertEquals('abc123', $json['labels'][0]['value']);
		$this->assertEquals('normal', $json['types'][0]['value']);
		$this->assertEquals(0, (float)$json['taxes'][0]['value']);

		$this->assertEquals('something', $json['labels'][1]['value']);
		$this->assertEquals('tax', $json['types'][1]['value']);
		$this->assertEquals(5.59, (float)$json['taxes'][1]['value']);

		$this->assertEquals('something else overwritten', $json['labels'][2]['value']);
		$this->assertEquals('tax', $json['types'][2]['value']);
		$this->assertEquals(150.85, (float)$json['taxes'][2]['value']);

	}

	public function test_if_it_saves_additional_product_fields_with_valid_data_provided_with_null_ids(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	null, /* null means new entries */
													'value'	=>	'abc123'
												]
											],
			'types'					=>		[
												[
													'id'	=>	null,
													'value'	=>	'normal'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	null,
													'value'	=>	'0.25'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-additional-product-fields?'. $params);
		
		$json = $response->json();
		
		$this->assertEquals('abc123', $json['labels'][0]['value']);
		$this->assertEquals('normal', $json['types'][0]['value']);
		$this->assertEquals(0.25, (float)$json['taxes'][0]['value']);

	}

	public function test_if_it_deletes_additional_product_fields_entry_with_valid_id_provided(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'normal'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0.25'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-additional-product-fields?'. $params);
		
		$json = $response->json();
		
		$this->assertEquals('abc123', $json['labels'][0]['value']);
		$this->assertEquals('normal', $json['types'][0]['value']);
		$this->assertEquals(0.25, (float)$json['taxes'][0]['value']);

		/* add temporary settings to test */
		$setting_section = new SettingsSection();
		$setting_section->company_id = $company_id;
		$setting_section->type = ISC_PRODUCT_COLUMNS_TYPE;
		$setting_section->settings_json = json_encode([[
														'id'		=>	1,
														'type'		=>	'custom',
														'test'		=>	'some value here',
														'value'		=>	'some value here',
														'mapped'	=>	'',
														'id_column'	=>	1
													]]);
		$setting_section->save();
		
		$before_delete_setting = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', ISC_PRODUCT_COLUMNS_TYPE]])->first();
		$this->assertNotEmpty(json_decode($before_delete_setting->settings_json, true));

		$response = $this->delete('/api/manage-invoice-settings-additional-product-fields/1', [
			'company_id'	=>	$company_id
		]);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);
		
		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-additional-product-fields?'. $params);
		
		$json = $response->json();
		$this->assertEmpty($json['labels']);
		$this->assertEmpty($json['types']);
		$this->assertEmpty($json['taxes']);

		$after_delete_setting = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', ISC_PRODUCT_COLUMNS_TYPE]])->first();
		$this->assertEmpty(json_decode($after_delete_setting->settings_json, true));

	}

	public function test_if_it_affects_product_columns_if_additional_fields_are_overwritten_for_default_data(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'normal'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0.25'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		/* overwrite */
		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123 edited'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'custom'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0.65'
												]
											]
		], $c['headers']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();
		
		$this->assertArrayHasKey('dropdown', $json);
		$this->assertEquals('abc123 edited', $json['dropdown'][0]['text']);
		$this->assertEquals('abc123 edited', $json['dropdown'][0]['value']);
		$this->assertEquals('custom', $json['dropdown'][0]['type']);
		$this->assertEquals(0.65, $json['dropdown'][0]['tax_rate']);

		

	}

	public function test_if_it_affects_product_columns_if_additional_fields_are_overwritten_for_saved_data(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'normal'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0.25'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);
		
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();
		
		$rows = array_merge($json['rows'], $json['dropdown']);
		
		/* save settings - not default */
		$response = $this->post('/api/manage-invoice-settings-product-columns', [
			'company_id'			=>		$company_id,
			'rows'					=>		$rows
		], $c['headers']);
		$response = $response->json();
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);
		
		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();
		
		$last_row = last($json['rows']);
		$this->assertEmpty($json['dropdown']);
		$this->assertEquals('abc123', $last_row['text']);
		$this->assertEquals('abc123', $last_row['value']);
		$this->assertEquals('custom', $last_row['type']);
		$this->assertEquals(0.25, $last_row['tax_rate']);
		/* overwrite */
		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123 edited'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'custom'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0.65'
												]
											]
		], $c['headers']);

		$json = $response->json();
		
		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();
		
		$last_row = last($json['rows']);
		$this->assertEmpty($json['dropdown']);
		$this->assertEquals('abc123 edited', $last_row['text']);
		$this->assertEquals('abc123 edited', $last_row['value']);
		$this->assertEquals('custom', $last_row['type']);
		$this->assertEquals(0.65, $last_row['tax_rate']);

	}


	public function test_if_it_affects_product_columns_if_additional_field_is_deleted_for_default_data(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												],
												[
													'id'	=>	2,
													'value'	=>	'some'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'normal'
												],
												[
													'id'	=>	2,
													'value'	=>	'custom'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0.25'
												],
												[
													'id'	=>	2,
													'value'	=>	'0'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		/* delete one field */
		$response = $this->delete('/api/manage-invoice-settings-additional-product-fields/2', [
			'ids'				=>	'',
			'company_id'		=>	$company_id
		], $c['headers']);
		

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();
		
		$this->assertArrayHasKey('dropdown', $json);
		$this->assertEquals('abc123', $json['dropdown'][0]['text']);
		$this->assertEquals('abc123', $json['dropdown'][0]['value']);
		$this->assertEquals('custom', $json['dropdown'][0]['type']);
		$this->assertEquals(0.25, $json['dropdown'][0]['tax_rate']);

		/* delete one more field */
		$response = $this->delete('/api/manage-invoice-settings-additional-product-fields/1', [
			'ids'				=>	'',
			'company_id'		=>	$company_id
		], $c['headers']);
		
		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();
		$this->assertArrayHasKey('dropdown', $json);
		$this->assertEmpty($json['dropdown']);

	}

	public function test_if_it_affects_product_columns_if_additional_field_is_deleted_for_saved_data(){

		SettingsSection::truncate();

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-additional-product-fields', [
			'company_id'			=>		$company_id,
			'labels'				=>		[
												[
													'id'	=>	1,
													'value'	=>	'abc123'
												],
												[
													'id'	=>	2,
													'value'	=>	'some'
												]
											],
			'types'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'normal'
												],
												[
													'id'	=>	2,
													'value'	=>	'custom'
												]
			],
			'taxes'					=>		[
												[
													'id'	=>	1,
													'value'	=>	'0.25'
												],
												[
													'id'	=>	2,
													'value'	=>	'0'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);
		
		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();

		$rows = array_merge($json['rows'], $json['dropdown']);
		
		/* save settings - not default */
		$response = $this->post('/api/manage-invoice-settings-product-columns', [
			'company_id'			=>		$company_id,
			'rows'					=>		$rows
		], $c['headers']);

		

		/* delete one field */
		$response = $this->delete('/api/manage-invoice-settings-additional-product-fields/2', [
			'ids'				=>	'',
			'company_id'		=>	$company_id
		], $c['headers']);
		
		
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();
		
		$last_row = last($json['rows']);
		
		$this->assertEmpty($json['dropdown']);
		$this->assertEquals('abc123', $last_row['text']);
		$this->assertEquals('abc123', $last_row['value']);
		$this->assertEquals('custom', $last_row['type']);
		$this->assertEquals(0.25, $last_row['tax_rate']);

		/* delete one more field */
		$response = $this->delete('/api/manage-invoice-settings-additional-product-fields/1', [
			'ids'				=>	'',
			'company_id'		=>	$company_id
		], $c['headers']);
		
		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		
		$json = $response->json();
		
		$last_row = last($json['rows']);
		$this->assertArrayHasKey('dropdown', $json);
		$this->assertEmpty($json['dropdown']);
		$this->assertNotEquals('abc123', $last_row['text']);

	}

}
