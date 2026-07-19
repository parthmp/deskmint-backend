<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\AccessTokenData;
use App\Models\AdditionalProductColumnsField;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoicesCustomField;
use App\Models\InvoiceSnapshot;
use App\Models\Product;
use App\Models\RefreshToken;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\GeneralFunctions;
use Tests\Traits\SetAccess;

class InvoiceControllerFetchTest extends TestCase
{
	use SettingsDefault, RefreshDatabase, SetAccess, DefaultCompany, CustomFields, GeneralFunctions;

	// protected function tearDown(): void
    // {
    //     Carbon::setTestNow(null);
    //     parent::tearDown();
    // }

	public function insertClient(int $company_id, mixed $headers, int $currency_id = 5) : Client {
		DB::table('clients')->truncate();
		$currency = Currency::where('id', '=', $currency_id)->first();
		$industry = Industry::inRandomOrder()->first();
		$country = Country::inRandomOrder()->first();
		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $headers);
		$response->assertStatus(200);
		return Client::first();
	}

	
	public function test_if_searches_clients_for_invoices_1_icft(){

		// Bus::fake();
		// Mail::fake();
		// Storage::fake(INVOICES_DISK);

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-clients?company_id='.$company_id.'&searched=yay', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		$this->assertEmpty($json);

	}

	public function test_if_searches_clients_for_invoices_2_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-clients?company_id='.$company_id.'&searched=test first', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$json = $json[0];
		$this->assertNotEmpty($json);
		$this->assertEquals(1, (int) $json['value']);
		$this->assertEquals(5, (int) $json['data']['currency']['id']);
		$this->assertEquals('USD', $json['data']['currency']['code']);

	}

	public function test_if_it_fetches_initial_data_default_data_3_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$default_product_columns = $this->getDefaultProductColumnsSettings($company_id);
		$default_total_fields = $this->getDefaultTotalFieldsSettings();
		
		$this->assertEquals(1, (int) $json['invoice_number']);
		$this->assertJsonWithoutIds($default_product_columns['rows'], $json['product_columns']);
		$this->assertJsonWithoutIds($default_total_fields['rows'], $json['total_fields']['rows']);
		$this->assertEmpty($json['custom_fields']);
		$this->assertEquals(2, (int) count($json['gateways']));

	}

	public function test_if_it_fetches_initial_data_invoice_number_change_4_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		//insert client
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.po_number', 'po number here');
		Arr::set($data, 'data.invoice_details.global_discount', '5');
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');
		Arr::set($data, 'data.invoice_terms', 'invoice terms here');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$default_product_columns = $this->getDefaultProductColumnsSettings($company_id);
		$default_total_fields = $this->getDefaultTotalFieldsSettings();
		
		$this->assertEquals(124, (int) $json['invoice_number']);
		$this->assertJsonWithoutIds($default_product_columns['rows'], $json['product_columns']);
		$this->assertJsonWithoutIds($default_total_fields['rows'], $json['total_fields']['rows']);
		$this->assertEmpty($json['custom_fields']);
		$this->assertEquals(2, (int) count($json['gateways']));

	}

	public function test_if_it_fetches_initial_data_invoice_number_pattern_change_5_icft(){

		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		//insert client
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.po_number', 'po number here');
		Arr::set($data, 'data.invoice_details.global_discount', '5');
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');
		Arr::set($data, 'data.invoice_terms', 'invoice terms here');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);

		//change the pattern
		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'		=>		'0001',
			'reset_counter'			=>		'weekly',
			'number_pattern'		=>		'INV---{$year}_{$month_number}_{$day_number}_{$day_name}{$day_number}{$month_number}{$month_full_name}-{$month_short_name}{$year}',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$default_product_columns = $this->getDefaultProductColumnsSettings($company_id);
		$default_total_fields = $this->getDefaultTotalFieldsSettings();
		
		$expected_date = Carbon::now('UTC')->addMinutes(330); // match this test's offset

		$expected_invoice_number = 'INV---'
			. $expected_date->format('Y') . '_'
			. $expected_date->format('m') . '_'
			. $expected_date->format('d') . '_'
			. $expected_date->format('l')
			. $expected_date->format('d')
			. $expected_date->format('m')
			. $expected_date->format('F') . '-'
			. $expected_date->format('M') . $expected_date->format('Y');

		if(strtolower(date('D')) === 'sun'){
			$this->assertEquals($expected_invoice_number.'0001', $json['invoice_number']);
		}else{
			$this->assertEquals($expected_invoice_number.'0124', $json['invoice_number']);
		}
		
		$this->assertJsonWithoutIds($default_product_columns['rows'], $json['product_columns']);
		$this->assertJsonWithoutIds($default_total_fields['rows'], $json['total_fields']['rows']);
		$this->assertEmpty($json['custom_fields']);
		$this->assertEquals(2, (int) count($json['gateways']));

	}

	public function test_if_it_fetches_initial_data_invoice_number_pattern_change_6_icft(){

		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		//change the pattern
		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'		=>		'1',
			'reset_counter'			=>		'monthly',
			'number_pattern'		=>		'INV---{$year}_{$month_number}_{$day_number}_{$day_name}{$day_number}{$month_number}{$month_full_name}-{$month_short_name}{$year}',
			'company_id'			=>		$company_id
		], $c['headers']);

		//insert client
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', 'INV---2026_07_15_Wednesday1507July-Jul20261265');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.po_number', 'po number here');
		Arr::set($data, 'data.invoice_details.global_discount', '5');
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');
		Arr::set($data, 'data.invoice_terms', 'invoice terms here');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);

		

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$default_product_columns = $this->getDefaultProductColumnsSettings($company_id);
		$default_total_fields = $this->getDefaultTotalFieldsSettings();
		
		$expected_date = Carbon::now('UTC')->addMinutes(330);

		$expected_invoice_number = 'INV---'
			. $expected_date->format('Y') . '_'
			. $expected_date->format('m') . '_'
			. $expected_date->format('d') . '_'
			. $expected_date->format('l')
			. $expected_date->format('d')
			. $expected_date->format('m')
			. $expected_date->format('F') . '-'
			. $expected_date->format('M') . $expected_date->format('Y')
			. '1266';
		$this->assertEquals($expected_invoice_number, $json['invoice_number']);
		$this->assertJsonWithoutIds($default_product_columns['rows'], $json['product_columns']);
		$this->assertJsonWithoutIds($default_total_fields['rows'], $json['total_fields']['rows']);
		$this->assertEmpty($json['custom_fields']);
		$this->assertEquals(2, (int) count($json['gateways']));

	}

	public function test_if_it_fetches_initial_data_invoice_number_pattern_change_with_reset_7_icft(){
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		//change the pattern
		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'		=>		'001',
			'reset_counter'			=>		'weekly',
			'number_pattern'		=>		'INV---{$year}_{$month_number}_{$day_number}_{$day_name}{$day_number}{$month_number}{$month_full_name}-{$month_short_name}{$year}',
			'company_id'			=>		$company_id
		], $c['headers']);
		
		

		//insert client
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', 'INV---2026_07_15_Wednesday1507July-Jul20261265');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.po_number', 'po number here');
		Arr::set($data, 'data.invoice_details.global_discount', '5');
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');
		Arr::set($data, 'data.invoice_terms', 'invoice terms here');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);
		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-numbers/reset-number?'. $params);
		$response->assertStatus(200);

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$default_product_columns = $this->getDefaultProductColumnsSettings($company_id);
		$default_total_fields = $this->getDefaultTotalFieldsSettings();
		
		$expected_date = Carbon::now('UTC')->addMinutes(330); // match whatever offset this test sends

		$expected_invoice_number = 'INV---'
			. $expected_date->format('Y') . '_'
			. $expected_date->format('m') . '_'
			. $expected_date->format('d') . '_'
			. $expected_date->format('l')
			. $expected_date->format('d')
			. $expected_date->format('m')
			. $expected_date->format('F') . '-'
			. $expected_date->format('M') . $expected_date->format('Y')
			. '001';

		$this->assertEquals($expected_invoice_number, $json['invoice_number']);
		$this->assertJsonWithoutIds($default_product_columns['rows'], $json['product_columns']);
		$this->assertJsonWithoutIds($default_total_fields['rows'], $json['total_fields']['rows']);
		$this->assertEmpty($json['custom_fields']);
		$this->assertEquals(2, (int) count($json['gateways']));

	}

	public function test_if_it_fetches_initial_data_with_product_columns_8_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		AdditionalProductColumnsField::factory()->create([
			'id'			=>	100,
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::factory()->create([
			'id'			=>	101,
			'company_id'	=>	$company_id,
			'type'			=>	'tax'
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
													'text'											=>	'tax123',
													'value'											=>	'5',
													'type'											=>	'custom',
													'mapped'										=>	'',
													'id_column'										=>	101
												]
											]
		], $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$default_total_fields = $this->getDefaultTotalFieldsSettings();
		
		$this->assertEquals(4, (int) count($json['product_columns']));
		$this->assertEquals('bla', $json['product_columns'][2]['text']);
		$this->assertEquals('tax123', $json['product_columns'][3]['text']);
		$this->assertJsonWithoutIds($default_total_fields['rows'], $json['total_fields']['rows']);
		$this->assertEmpty($json['custom_fields']);
		$this->assertEquals(2, (int) count($json['gateways']));

	}

	public function test_if_it_fetches_initial_data_with_total_fields_9_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->post('/api/manage-invoice-settings-total-fields', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'		=>	1,
													'text'		=>	'Net Subtotal',
													'value'		=>	General::replaceWithUnderscores('Net Subtotal'),
													'mapped'	=>	['net_subtotal'],
													'type'		=>	'normal'
												],
												[
													'id'		=>	2,
													'text'		=>	'Subtotal',
													'value'		=>	General::replaceWithUnderscores('Subtotal'),
													'mapped'	=>	['sub_total'],
													'type'		=>	'normal'
												],
												[
													'id'		=>	3,
													'text'		=>	'Discount',
													'value'		=>	General::replaceWithUnderscores('Discount'),
													'mapped'	=>	['discount'],
													'type'		=>	'normal'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		
		$this->assertEquals(3, (int) count($json['total_fields']));
		$this->assertEquals('Net Subtotal', $json['total_fields'][0]['text']);
		$this->assertEquals('Subtotal', $json['total_fields'][1]['text']);
		$this->assertEquals('Discount', $json['total_fields'][2]['text']);
		$this->assertEmpty($json['custom_fields']);
		$this->assertEquals(2, (int) count($json['gateways']));

	}

	public function test_if_it_fetches_initial_data_with_custom_fields_10_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals(10, (int) count($json['custom_fields']));
		$this->assertEquals(2, (int) count($json['gateways']));

	}

	public function test_if_it_fetches_initial_data_with_payment_gateways_10_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$client_id = 'abc 123';
		$secret = 'secret api key';
		$mode = 'sandbox';
		$app_id = 'app id';
		$webhook_id = 'webhook id';

		$response = $this->post('/api/manage-paypal-settings', [
			'company_id' 	=>	$company_id,
			'client_id'		=>	$client_id,
			'secret'		=>	$secret,
			'mode'			=>	$mode,
			'app_id'		=>	$app_id,
			'webhook_id'	=>	$webhook_id,
		], $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals(3, (int) count($json['gateways']));
		$this->assertEquals('PayPal', $json['gateways'][2]['text']);

	}

	public function test_if_it_fetches_initial_data_with_payment_gateways_11_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$client_id = 'abc 123';
		$secret = 'secret api key';
		$mode = 'sandbox';
		$app_id = 'app id';
		$webhook_id = 'webhook id';

		$response = $this->post('/api/manage-paypal-settings', [
			'company_id' 	=>	$company_id,
			'client_id'		=>	$client_id,
			'secret'		=>	$secret,
			'mode'			=>	$mode,
			'app_id'		=>	$app_id,
			'webhook_id'	=>	$webhook_id,
		], $c['headers']);

		$secret = 'secret api key';
		$webhook_secret = 'webhook secret api key';

		$response = $this->post('/api/manage-stripe-settings', [
			'company_id' 			=>	$company_id,
			'secret'				=>	$secret,
			'webhook_secret'		=>	$webhook_secret
		], $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals(4, (int) count($json['gateways']));
		$this->assertEquals('PayPal', $json['gateways'][2]['text']);
		$this->assertEquals('Stripe', $json['gateways'][3]['text']);

	}

	public function test_if_it_fetches_products_12_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-products?company_id='.$company_id.'&searched=whatever', $c['headers']);
		$json = $response->json();
		
		$this->assertEmpty($json);
		
	}

	public function test_if_it_fetches_products_13_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'test product',
			'price'						=>	'99.95',
			'sku'						=>	'SKU 123',
			'description'				=>	'whatever here',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'some else',
			'price'						=>	'5',
			'sku'						=>	'456',
			'description'				=>	'desc',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-products?company_id='.$company_id.'&searched=whatever', $c['headers']);
		$json = $response->json();
		$this->assertEmpty($json);
		
	}

	public function test_if_it_fetches_products_14_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'test product',
			'price'						=>	'99.95',
			'sku'						=>	'SKU 123',
			'description'				=>	'whatever here',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'some else',
			'price'						=>	'5',
			'sku'						=>	'456',
			'description'				=>	'desc',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-products?company_id='.$company_id.'&searched=some', $c['headers']);
		$json = $response->json();

		$this->assertNotEmpty($json);
		$this->assertEquals('some else', $json[0]['data']['product']['product_name']);
		
	}

	public function test_if_it_fetches_products_15_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'test product',
			'price'						=>	'99.95',
			'sku'						=>	'SKU 123',
			'description'				=>	'whatever here',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'some else',
			'price'						=>	'5',
			'sku'						=>	'456',
			'description'				=>	'desc',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'thing some',
			'price'						=>	'5',
			'sku'						=>	'456',
			'description'				=>	'desc',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response = $this->get('/api/manage-invoices/fetch-products?company_id='.$company_id.'&searched=some', $c['headers']);
		$json = $response->json();
		
		$this->assertNotEmpty($json);
		$this->assertEquals(2, (int) count($json));
		$this->assertEquals('some else', $json[0]['data']['product']['product_name']);
		$this->assertEquals('thing some', $json[1]['data']['product']['product_name']);
		
	}

	public function test_if_it_fetches_index_default_16_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$response = $this->get('/api/manage-invoices?company_id='.$company_id.'&default_per_page=5', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals(9, (int) count($json['table_data']['columns']));
		
	}

	public function test_if_it_fetches_invoice_for_edit_17_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		//insert invoice
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];
		
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

		$paypal_settings = json_encode([
			'secret'		=>	encrypt(env('TEST_STRIPE_SECRET_KEY')),
			'webhook_secret'	=>	encrypt(env('TEST_STRIPE_WEBHOOK_SECRET_KEY')),
		]);

		SettingsSection::insert([
			'id'	=>	1,
			'company_id'	=>	$company_id,
			'type'	=>	PAYMENTS_STRIPE_TYPE,
			'settings_json'	=>	$paypal_settings
		]);

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"discount" 				=> 0,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$response = $this->get('/api/manage-invoices/1?company_id='.$company_id, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals(1, (int) $json['invoice']['id']);
		$this->assertNull($json['invoice']['deleted_at']);
		$this->assertEquals('123', $json['invoice']['invoice_number']);
		$this->assertEquals('2026-06-23T14:32:47.000000Z', $json['invoice']['invoice_date']);
		$this->assertEquals('2026-06-23T14:32:47.000000Z', $json['invoice']['due_date']);
		$this->assertEquals('', $json['invoice']['po_number']);
		$this->assertEquals('16.33', $json['invoice']['balance_due']);
		$this->assertEquals('16.33', $json['invoice']['total']);
		$this->assertEquals('1', $json['invoice']['company_id']);
		$this->assertEquals('1', $json['invoice']['client_id']);
		$this->assertEquals('0', $json['invoice']['discount']);
		$this->assertEquals('1', $json['invoice']['discount_type']);
		$this->assertEquals('0', $json['invoice']['discount_amount_post_tax']);
		$this->assertEquals('15.55', $json['invoice']['subtotal']);
		$this->assertEquals('0.78', $json['invoice']['tax_amount']);
		$this->assertEquals('', $json['invoice']['invoice_terms']);
		$this->assertEquals('2', $json['invoice']['payment_method']);
		$this->assertEquals('0', $json['invoice']['discount_amount_pre_tax']);
		$this->assertEquals('330', $json['invoice']['timezone_offset_minutes']);
		$this->assertEquals('test firstname', $json['invoice']['first_name']);
		$this->assertEquals('test lastname', $json['invoice']['last_name']);
		$this->assertEquals('test firstname test lastname', $json['invoice']['full_name']);
		$this->assertEquals('', $json['invoice']['client_company']);
		$this->assertEquals('5', $json['invoice']['currency_id']);
		$this->assertEquals('1', $json['invoice']['status']);
		$this->assertNotEmpty($json['invoice']['pdf_file']);
		$this->assertEmpty($json['invoice']['xml_file']);
		$this->assertEquals('0', $json['invoice']['reminders_sent']);
		$this->assertEquals('0', $json['invoice']['refunded_amount']);
		$this->assertEquals('USD', $json['invoice']['currency_code']);
		$this->assertNotEmpty($json['invoice']['last_reminder_sent_at']);

		$this->assertEquals(10, (int) count($json['custom_fields']));

		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][0]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][1]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][2]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][3]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][4]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][5]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][6]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][7]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][8]);
		$this->assertArrayHasKey('invoices_custom_field', $json['custom_fields'][9]);

		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][0]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][1]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][2]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][3]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][4]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][5]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][6]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][7]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][8]['invoices_custom_field']);
		$this->assertArrayHasKey('custom_field_type', $json['custom_fields'][9]['invoices_custom_field']);


		$this->assertEquals('some text', $json['custom_fields'][0]['field_value']);
		$this->assertEquals('some textarea text', $json['custom_fields'][1]['field_value']);
		$this->assertEquals('email@value.com', $json['custom_fields'][2]['field_value']);
		$this->assertEquals('one', $json['custom_fields'][3]['field_value']);
		$this->assertEquals('1234678', $json['custom_fields'][4]['field_value']);
		$this->assertEquals('2018-01-20T00:00:00.000Z', $json['custom_fields'][5]['field_value']);
		$this->assertEquals('10:15 AM', $json['custom_fields'][6]['field_value']);
		$this->assertEquals('2018-01-19T11:08:15Z', $json['custom_fields'][7]['field_value']);
		$this->assertEquals('+123457890', $json['custom_fields'][8]['field_value']);
		$this->assertEquals('["one"]', $json['custom_fields'][9]['field_value']);


		$this->assertEquals('bla-123', $json['product_rows'][0]['row_uuid']);
		$this->assertEquals('15.55', $json['product_rows'][0]['line_subtotal']);
		$this->assertEquals('0.78', $json['product_rows'][0]['tax_amount']);
		$this->assertEquals('16.33', $json['product_rows'][0]['line_total']);
		$this->assertEquals('1', $json['product_rows'][0]['product_id']);
		$this->assertEquals('prod 3 desc', $json['product_rows'][0]['description']);
		$this->assertEquals('15.55', $json['product_rows'][0]['unit_price']);
		$this->assertEquals('1', $json['product_rows'][0]['quantity']);
		$this->assertEquals('0', $json['product_rows'][0]['discount']);
		$this->assertEquals('5', $json['product_rows'][0]['tax']);


		$this->assertFalse($json['locked']);
		$this->assertFalse($json['cancelled']);
		
	}

	public function test_if_it_fetches_invoice_for_snapshot_18_icft(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		//insert invoice
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];
		
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

		$paypal_settings = json_encode([
			'secret'		=>	encrypt(env('TEST_STRIPE_SECRET_KEY')),
			'webhook_secret'	=>	encrypt(env('TEST_STRIPE_WEBHOOK_SECRET_KEY')),
		]);

		SettingsSection::insert([
			'id'	=>	1,
			'company_id'	=>	$company_id,
			'type'	=>	PAYMENTS_STRIPE_TYPE,
			'settings_json'	=>	$paypal_settings
		]);

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"discount" 				=> 0,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$response = $this->get('/api/manage-invoices/snapshot/1?company_id='.$company_id, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$snapshot = InvoiceSnapshot::where('invoice_id', '=', 1)->first();

		$this->assertEquals($snapshot->snapshot, $json);
		
		
	}

	public function test_if_it_fetches_initial_data_invoice_number_pattern_change_19_icft(){
		
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();
		
		$client = $this->insertClient($company_id, $c['headers']);

		//insert client
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.po_number', 'po number here');
		Arr::set($data, 'data.invoice_details.global_discount', '5');
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');
		Arr::set($data, 'data.invoice_terms', 'invoice terms here');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);

		//change the pattern
		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'		=>		'0001',
			'reset_counter'			=>		'monthly',
			'number_pattern'		=>		'INV---{$year}_{$month_number}_{$day_number}_{$day_name}{$day_number}{$month_number}{$month_full_name}-{$month_short_name}{$year}',
			'company_id'			=>		$company_id
		], $c['headers']);
		
		Carbon::setTestNow(Carbon::create(2026, 1, 1, 12, 0, 0));
		AccessTokenData::where('device', $device)->update(['created_at' => now()]);
		RefreshToken::where('device', $device)->update(['created_at' => now()]);
		
		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$default_product_columns = $this->getDefaultProductColumnsSettings($company_id);
		$default_total_fields = $this->getDefaultTotalFieldsSettings();
		
		$expected_date = Carbon::now('UTC')->addMinutes(330); // match this test's offset

		$expected_invoice_number = 'INV---'
			. $expected_date->format('Y') . '_'
			. $expected_date->format('m') . '_'
			. $expected_date->format('d') . '_'
			. $expected_date->format('l')
			. $expected_date->format('d')
			. $expected_date->format('m')
			. $expected_date->format('F') . '-'
			. $expected_date->format('M') . $expected_date->format('Y');

		
		$this->assertEquals($expected_invoice_number.'0001', $json['invoice_number']);
		
		$this->assertJsonWithoutIds($default_product_columns['rows'], $json['product_columns']);
		$this->assertJsonWithoutIds($default_total_fields['rows'], $json['total_fields']['rows']);
		$this->assertEmpty($json['custom_fields']);
		$this->assertEquals(2, (int) count($json['gateways']));

		Carbon::setTestNow();

	}



}
