<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\AdditionalProductColumnsField;
use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceSettingsProductColumnsControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	public function test_if_it_fails_data_for_product_columns_invoice_settings_with_invalid_data() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-product-columns', [
			'company_id'			=>		$company_id,
			'rows'					=>		[]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(1, count($json['errors']));

	}

	public function test_if_it_fails_for_item_product_columns_invoice_settings_with_for_custom_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		/* add invoice custom field */
		AdditionalProductColumnsField::factory()->create([
			'id'			=>	100,
			'company_id'	=>	$company_id
		]);

		
		$response = $this->post('/api/manage-invoice-settings-product-columns', [
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
		
		$response->assertStatus((int) config('global.error_code'));
		
		$this->assertArrayHasKey('validity', $response);
		/* won't allow it as Item is missing in array */
		$this->assertEquals('deletion_not_allowed', $response['validity']);

		/* now check if it was saved */
		// $settings = SettingsSection::where([['type', '=', ISC_PRODUCT_COLUMNS_TYPE], ['company_id', '=', $company_id]])->first();
		// $settings = json_decode($settings->settings_json, true);
		
		// $this->assertEquals([[
		// 						'id'						=>	1,
		// 						'text'						=>	'bla',
		// 						'value'						=>	'bla',
		// 						'type'						=>	'custom',
		// 						'id_column'					=>	100
		// 					]], $settings);
	}

	public function test_if_it_saves_data_for_product_columns_invoice_settings_with_both_field_types() : void{
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		AdditionalProductColumnsField::factory()->create([
			'id'			=>	100,
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::factory()->create([
			'id'			=>	101,
			'company_id'	=>	$company_id
		]);

		
		$response = $this->post('/api/manage-invoice-settings-product-columns', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'		=>	1,
													'text'		=>	'Item',
													'value'		=>	General::replaceWithUnderscores('Item'),
													'mapped'	=>	['product_id'], /* from db */
													'type'		=>	'normal'
												],
												[
													'id'		=>	2,
													'text'		=>	'Description',
													'value'		=>	General::replaceWithUnderscores('Description'),
													'mapped'	=>	['description'],
													'type'		=>	'normal'
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

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		$json = $response->json();
		
		$this->assertEquals(5, count($json['dropdown']));
		$this->assertEquals(3, count($json['rows']));
	}

	public function test_if_it_overwrites_data_for_product_columns_invoice_settings_with_both_field_types() : void{
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		AdditionalProductColumnsField::factory()->create([
			'id'			=>	100,
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::factory()->create([
			'id'			=>	101,
			'company_id'	=>	$company_id
		]);

		
		$response = $this->post('/api/manage-invoice-settings-product-columns', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'		=>	1,
													'text'		=>	'Item',
													'value'		=>	General::replaceWithUnderscores('Item'),
													'mapped'	=>	['product_id'], /* from db */
													'type'		=>	'normal'
												],
												[
													'id'		=>	2,
													'text'		=>	'Description',
													'value'		=>	General::replaceWithUnderscores('Description'),
													'mapped'	=>	['description'],
													'type'		=>	'normal'
												],
												[
													'id'											=>	2,
													'text'											=>	'bla',
													'value'											=>	'bla',
													'type'											=>	'custom',
													'mapped'										=>	'',
													'id_column'										=>	100
												],
												[
													'id'											=>	2,
													'text'											=>	'bla new',
													'value'											=>	'bla new',
													'type'											=>	'custom',
													'mapped'										=>	'',
													'id_column'										=>	101
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		$json = $response->json();
		
		$this->assertEquals('Item', $json['rows'][0]['text']);
		$this->assertEquals('Description', $json['rows'][1]['text']);
		$this->assertEquals('bla', $json['rows'][2]['text']);
		$this->assertEquals('bla new', $json['rows'][3]['text']);

		$response = $this->post('/api/manage-invoice-settings-product-columns', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'		=>	1,
													'text'		=>	'Item overwritten',
													'value'		=>	General::replaceWithUnderscores('Item'),
													'mapped'	=>	['product_id'], /* from db */
													'type'		=>	'normal'
												],
												[
													'id'		=>	2,
													'text'		=>	'Description overwritten',
													'value'		=>	General::replaceWithUnderscores('Description'),
													'mapped'	=>	['description'],
													'type'		=>	'normal'
												],
												[
													'id'											=>	2,
													'text'											=>	'bla',
													'value'											=>	'bla',
													'type'											=>	'custom',
													'mapped'										=>	'',
													'id_column'										=>	100
												],
												[
													'id'											=>	2,
													'text'											=>	'bla new overwritten',
													'value'											=>	'bla new',
													'type'											=>	'custom',
													'mapped'										=>	'',
													'id_column'										=>	101
												]
											]
		], $c['headers']);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-product-columns?'. $params);
		$json = $response->json();
		
		$this->assertEquals('Item overwritten', $json['rows'][0]['text']);
		$this->assertEquals('Description overwritten', $json['rows'][1]['text']);
		$this->assertEquals('bla', $json['rows'][2]['text']);
		$this->assertEquals('bla new overwritten', $json['rows'][3]['text']);

	}

}
