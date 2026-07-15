<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\AdditionalProductColumnsField;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
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
		
		$this->assertEquals('INV---'.date('Y').'_'.date('m').'_'.date('d').'_'.date('l').''.date('d').''.date('m').''.date('F').'-'.date('M').date('Y').'0124', $json['invoice_number']);
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

		

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$default_product_columns = $this->getDefaultProductColumnsSettings($company_id);
		$default_total_fields = $this->getDefaultTotalFieldsSettings();
		
		$this->assertEquals('INV---'.date('Y').'_'.date('m').'_'.date('d').'_'.date('l').''.date('d').''.date('m').''.date('F').'-'.date('M').date('Y').'1266', $json['invoice_number']);
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
		
		$this->assertEquals('INV---'.date('Y').'_'.date('m').'_'.date('d').'_'.date('l').''.date('d').''.date('m').''.date('F').'-'.date('M').date('Y').'001', $json['invoice_number']);
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

}
