<?php

namespace Tests\Feature;

use App\Models\AdditionalProductColumnsField;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

class InvoiceCalculationTest extends TestCase {

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

	public function test_if_invoice_calculations_work_1_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

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
				"unit_price" 		=> 150,
				"quantity" 			=> 1,
				"tax" 				=> 0,
				"discount" 			=> 33.33,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$default_product_rows_settings = json_encode($this->getDefaultProductColumnsSettings($company_id)['rows']);

		$invoice = Invoice::first();

		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(100.01, $invoice->balance_due);
		$this->assertEquals(100.01, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(0, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(0, $invoice->discount_amount_post_tax);
		$this->assertEquals(50, $invoice->discount_amount_pre_tax);
		$this->assertEquals(150, $invoice->subtotal);
		$this->assertEquals(0, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

	}

	public function test_if_invoice_calculations_work_2_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

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
				"unit_price" 		=> 150,
				"quantity" 			=> 1,
				"tax" 				=> 20,
				"discount" 			=> 33.33,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$default_product_rows_settings = json_encode($this->getDefaultProductColumnsSettings($company_id)['rows']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(120.01, $invoice->balance_due);
		$this->assertEquals(120.01, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(0, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(0, $invoice->discount_amount_post_tax);
		$this->assertEquals(50, $invoice->discount_amount_pre_tax);
		$this->assertEquals(150, $invoice->subtotal);
		$this->assertEquals(20, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

	}

	public function test_if_invoice_calculations_work_3_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

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
				"unit_price" 		=> 85.99,
				"quantity" 			=> 1,
				"tax" 				=> 14.58,
				"discount" 			=> 5.85,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$default_product_rows_settings = json_encode($this->getDefaultProductColumnsSettings($company_id)['rows']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(92.76, $invoice->balance_due);
		$this->assertEquals(92.76, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(0, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(0, $invoice->discount_amount_post_tax);
		$this->assertEquals(5.03, $invoice->discount_amount_pre_tax);
		$this->assertEquals(85.99, $invoice->subtotal);
		$this->assertEquals(11.8, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

	}

	public function test_if_invoice_calculations_work_4_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::insert([
			[
				'company_id'	=>	$company_id,
				'label'			=>	'c field',
				'type'			=>	'normal',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 1',
				'type'			=>	'tax',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 2',
				'type'			=>	'tax',
				'tax_rate'		=>	'0'
			]
		]);
		
		$json_string = '[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]';

		SettingsSection::insert([
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	$json_string
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 85.99,
				"quantity" 				=> 1,
				"tax" 					=> 14.58,
				"discount" 				=> 5.85,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 9.99,
				"custom_tax_ctax_2" 	=> 4.71,
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(104.66, $invoice->balance_due);
		$this->assertEquals(104.66, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(0, $invoice->is_paid);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(0, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(0, $invoice->discount_amount_post_tax);
		$this->assertEquals(5.03, $invoice->discount_amount_pre_tax);
		$this->assertEquals(85.99, $invoice->subtotal);
		$this->assertEquals(23.71, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($json_string, true));

		$items = InvoiceItem::all();

		$item1 = $items[0];

		$this->assertEquals('bla-123', $item1->row_uuid);
		$this->assertEquals($invoice->id, $item1->invoice_id);
		$this->assertEquals(1, $item1->product_id);
		$this->assertEquals(85.99, $item1->unit_price);
		$this->assertEquals(1, $item1->quantity);
		$this->assertEquals(5.85, $item1->discount);
		$this->assertEquals(5.03, $item1->discount_amount);
		$this->assertEquals(14.58, $item1->tax);
		$this->assertEquals(23.71, $item1->tax_amount);
		$this->assertEquals(104.66, $item1->line_total);
		$this->assertEquals(80.96, $item1->line_subtotal);

	}

	public function test_if_invoice_calculations_work_5_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::insert([
			[
				'company_id'	=>	$company_id,
				'label'			=>	'c field',
				'type'			=>	'normal',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 1',
				'type'			=>	'tax',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 2',
				'type'			=>	'tax',
				'tax_rate'		=>	'0'
			]
		]);
		
		$json_string = '[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]';

		SettingsSection::insert([
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	$json_string
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 85.99,
				"quantity" 				=> 1,
				"tax" 					=> 14.58,
				"discount" 				=> 5.85,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 9.99,
				"custom_tax_ctax_2" 	=> 4.71,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-2",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 85.99,
				"quantity" 				=> 1,
				"tax" 					=> 14.58,
				"discount" 				=> 5.85,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 9.99,
				"custom_tax_ctax_2" 	=> 4.71,
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(209.33, $invoice->balance_due);
		$this->assertEquals(209.33, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(0, $invoice->is_paid);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(0, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(0, $invoice->discount_amount_post_tax);
		$this->assertEquals(10.06, $invoice->discount_amount_pre_tax);
		$this->assertEquals(171.98, $invoice->subtotal);
		$this->assertEquals(47.41, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($json_string, true));

		$items = InvoiceItem::all();

		$item1 = $items[0];
		$item2 = $items[1];

		$this->assertEquals('bla-123', $item1->row_uuid);
		$this->assertEquals($invoice->id, $item1->invoice_id);
		$this->assertEquals(1, $item1->product_id);
		$this->assertEquals(85.99, $item1->unit_price);
		$this->assertEquals(1, $item1->quantity);
		$this->assertEquals(5.85, $item1->discount);
		$this->assertEquals(5.03, $item1->discount_amount);
		$this->assertEquals(14.58, $item1->tax);
		$this->assertEquals(23.71, $item1->tax_amount);
		$this->assertEquals(104.66, $item1->line_total);
		$this->assertEquals(80.96, $item1->line_subtotal);

		
		$this->assertEquals('bla-123-2', $item2->row_uuid);
		$this->assertEquals($invoice->id, $item2->invoice_id);
		$this->assertEquals(1, $item2->product_id);
		$this->assertEquals(85.99, $item2->unit_price);
		$this->assertEquals(1, $item2->quantity);
		$this->assertEquals(5.85, $item2->discount);
		$this->assertEquals(5.03, $item2->discount_amount);
		$this->assertEquals(14.58, $item2->tax);
		$this->assertEquals(23.71, $item2->tax_amount);
		$this->assertEquals(104.66, $item2->line_total);
		$this->assertEquals(80.96, $item2->line_subtotal);


	}

	public function test_if_invoice_calculations_work_6_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::insert([
			[
				'company_id'	=>	$company_id,
				'label'			=>	'c field',
				'type'			=>	'normal',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 1',
				'type'			=>	'tax',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 2',
				'type'			=>	'tax',
				'tax_rate'		=>	'0'
			]
		]);
		
		$json_string = '[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]';

		SettingsSection::insert([
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	$json_string
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 507.44,
				"quantity" 				=> 1,
				"tax" 					=> 9,
				"discount" 				=> 24.05,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 1,
				"custom_tax_ctax_2" 	=> 0,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-2",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 1.05,
				"quantity" 				=> 3,
				"tax" 					=> 6.50,
				"discount" 				=> 0,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 0,
				"custom_tax_ctax_2" 	=> 8,
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(427.55, $invoice->balance_due);
		$this->assertEquals(427.55, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(0, $invoice->is_paid);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(0, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(0, $invoice->discount_amount_post_tax);
		$this->assertEquals(122.04, $invoice->discount_amount_pre_tax);
		$this->assertEquals(510.59, $invoice->subtotal);
		$this->assertEquals(39, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($json_string, true));

		$items = InvoiceItem::all();

		$item1 = $items[0];
		$item2 = $items[1];

		$this->assertEquals('bla-123', $item1->row_uuid);
		$this->assertEquals($invoice->id, $item1->invoice_id);
		$this->assertEquals(1, $item1->product_id);
		$this->assertEquals(507.44, $item1->unit_price);
		$this->assertEquals(1, $item1->quantity);
		$this->assertEquals(24.05, $item1->discount);
		$this->assertEquals(122.04, $item1->discount_amount);
		$this->assertEquals(9, $item1->tax);
		$this->assertEquals(38.54, $item1->tax_amount);
		$this->assertEquals(423.94, $item1->line_total);
		$this->assertEquals(385.40, $item1->line_subtotal);

		
		$this->assertEquals('bla-123-2', $item2->row_uuid);
		$this->assertEquals($invoice->id, $item2->invoice_id);
		$this->assertEquals(1, $item2->product_id);
		$this->assertEquals(1.05, $item2->unit_price);
		$this->assertEquals(3, $item2->quantity);
		$this->assertEquals(0, $item2->discount);
		$this->assertEquals(0, $item2->discount_amount);
		$this->assertEquals(6.50, $item2->tax);
		$this->assertEquals(0.46, $item2->tax_amount);
		$this->assertEquals(3.61, $item2->line_total);
		$this->assertEquals(3.15, $item2->line_subtotal);


	}

	public function test_if_invoice_calculations_work_7_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::insert([
			[
				'company_id'	=>	$company_id,
				'label'			=>	'c field',
				'type'			=>	'normal',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 1',
				'type'			=>	'tax',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 2',
				'type'			=>	'tax',
				'tax_rate'		=>	'0'
			]
		]);
		
		$json_string = '[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]';

		SettingsSection::insert([
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	$json_string
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 10,
				"quantity" 				=> 500,
				"tax" 					=> 9,
				"discount" 				=> 5.60,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 9,
				"custom_tax_ctax_2" 	=> 0,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b39",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-2",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 77.77,
				"quantity" 				=> 2,
				"tax" 					=> 10.50,
				"discount" 				=> 5,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 6.32,
				"custom_tax_ctax_2" 	=> 3.21,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b55",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-3",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 205.47,
				"quantity" 				=> 1,
				"tax" 					=> 3.21,
				"discount" 				=> 4.21,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 12.25,
				"custom_tax_ctax_2" 	=> 6.66,
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(5987.32, $invoice->balance_due);
		$this->assertEquals(5987.32, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(0, $invoice->is_paid);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(0, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(0, $invoice->discount_amount_post_tax);
		$this->assertEquals(296.43, $invoice->discount_amount_pre_tax);
		$this->assertEquals(5361.01, $invoice->subtotal);
		$this->assertEquals(922.73, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($json_string, true));

		$items = InvoiceItem::all();

		$item1 = $items[0];
		$item2 = $items[1];
		$item3 = $items[2];

		$this->assertEquals('bla-123', $item1->row_uuid);
		$this->assertEquals($invoice->id, $item1->invoice_id);
		$this->assertEquals(1, $item1->product_id);
		$this->assertEquals(10, $item1->unit_price);
		$this->assertEquals(500, $item1->quantity);
		$this->assertEquals(5.60, $item1->discount);
		$this->assertEquals(280, $item1->discount_amount);
		$this->assertEquals(9, $item1->tax);
		$this->assertEquals(849.60, $item1->tax_amount);
		$this->assertEquals(5569.60, $item1->line_total);
		$this->assertEquals(4720, $item1->line_subtotal);

		
		$this->assertEquals('bla-123-2', $item2->row_uuid);
		$this->assertEquals($invoice->id, $item2->invoice_id);
		$this->assertEquals(1, $item2->product_id);
		$this->assertEquals(77.77, $item2->unit_price);
		$this->assertEquals(2, $item2->quantity);
		$this->assertEquals(5, $item2->discount);
		$this->assertEquals(7.78, $item2->discount_amount);
		$this->assertEquals(10.50, $item2->tax);
		$this->assertEquals(29.60, $item2->tax_amount);
		$this->assertEquals(177.36, $item2->line_total);
		$this->assertEquals(147.76, $item2->line_subtotal);

		$this->assertEquals('bla-123-3', $item3->row_uuid);
		$this->assertEquals($invoice->id, $item3->invoice_id);
		$this->assertEquals(1, $item3->product_id);
		$this->assertEquals(205.47, $item3->unit_price);
		$this->assertEquals(1, $item3->quantity);
		$this->assertEquals(4.21, $item3->discount);
		$this->assertEquals(8.65, $item3->discount_amount);
		$this->assertEquals(3.21, $item3->tax);
		$this->assertEquals(43.54, $item3->tax_amount);
		$this->assertEquals(240.36, $item3->line_total);
		$this->assertEquals(196.82, $item3->line_subtotal);


	}

	public function test_if_invoice_calculations_work_8_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.global_discount', 120);
		Arr::set($data, 'data.invoice_details.global_discount_type', 'amount');


		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::insert([
			[
				'company_id'	=>	$company_id,
				'label'			=>	'c field',
				'type'			=>	'normal',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 1',
				'type'			=>	'tax',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 2',
				'type'			=>	'tax',
				'tax_rate'		=>	'0'
			]
		]);
		
		$json_string = '[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]';

		SettingsSection::insert([
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	$json_string
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 10,
				"quantity" 				=> 500,
				"tax" 					=> 9,
				"discount" 				=> 5.60,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 9,
				"custom_tax_ctax_2" 	=> 0,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b39",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-2",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 77.77,
				"quantity" 				=> 2,
				"tax" 					=> 10.50,
				"discount" 				=> 5,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 6.32,
				"custom_tax_ctax_2" 	=> 3.21,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b55",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-3",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 205.47,
				"quantity" 				=> 1,
				"tax" 					=> 3.21,
				"discount" 				=> 4.21,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 12.25,
				"custom_tax_ctax_2" 	=> 6.66,
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(5867.32, $invoice->balance_due);
		$this->assertEquals(5867.32, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(0, $invoice->is_paid);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(120, $invoice->discount);
		$this->assertEquals(2, $invoice->discount_type);
		$this->assertEquals(120, $invoice->discount_amount_post_tax);
		$this->assertEquals(296.43, $invoice->discount_amount_pre_tax);
		$this->assertEquals(5361.01, $invoice->subtotal);
		$this->assertEquals(922.73, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($json_string, true));

		$items = InvoiceItem::all();

		$item1 = $items[0];
		$item2 = $items[1];
		$item3 = $items[2];

		$this->assertEquals('bla-123', $item1->row_uuid);
		$this->assertEquals($invoice->id, $item1->invoice_id);
		$this->assertEquals(1, $item1->product_id);
		$this->assertEquals(10, $item1->unit_price);
		$this->assertEquals(500, $item1->quantity);
		$this->assertEquals(5.60, $item1->discount);
		$this->assertEquals(280, $item1->discount_amount);
		$this->assertEquals(9, $item1->tax);
		$this->assertEquals(849.60, $item1->tax_amount);
		$this->assertEquals(5569.60, $item1->line_total);
		$this->assertEquals(4720, $item1->line_subtotal);

		
		$this->assertEquals('bla-123-2', $item2->row_uuid);
		$this->assertEquals($invoice->id, $item2->invoice_id);
		$this->assertEquals(1, $item2->product_id);
		$this->assertEquals(77.77, $item2->unit_price);
		$this->assertEquals(2, $item2->quantity);
		$this->assertEquals(5, $item2->discount);
		$this->assertEquals(7.78, $item2->discount_amount);
		$this->assertEquals(10.50, $item2->tax);
		$this->assertEquals(29.60, $item2->tax_amount);
		$this->assertEquals(177.36, $item2->line_total);
		$this->assertEquals(147.76, $item2->line_subtotal);

		$this->assertEquals('bla-123-3', $item3->row_uuid);
		$this->assertEquals($invoice->id, $item3->invoice_id);
		$this->assertEquals(1, $item3->product_id);
		$this->assertEquals(205.47, $item3->unit_price);
		$this->assertEquals(1, $item3->quantity);
		$this->assertEquals(4.21, $item3->discount);
		$this->assertEquals(8.65, $item3->discount_amount);
		$this->assertEquals(3.21, $item3->tax);
		$this->assertEquals(43.54, $item3->tax_amount);
		$this->assertEquals(240.36, $item3->line_total);
		$this->assertEquals(196.82, $item3->line_subtotal);


	}

	public function test_if_invoice_calculations_work_9_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.global_discount', 87.77);
		Arr::set($data, 'data.invoice_details.global_discount_type', 'amount');


		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::insert([
			[
				'company_id'	=>	$company_id,
				'label'			=>	'c field',
				'type'			=>	'normal',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 1',
				'type'			=>	'tax',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 2',
				'type'			=>	'tax',
				'tax_rate'		=>	'0'
			]
		]);
		
		$json_string = '[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]';

		SettingsSection::insert([
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	$json_string
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 10,
				"quantity" 				=> 500,
				"tax" 					=> 9,
				"discount" 				=> 5.60,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 9,
				"custom_tax_ctax_2" 	=> 0,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b39",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-2",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 77.77,
				"quantity" 				=> 2,
				"tax" 					=> 10.50,
				"discount" 				=> 5,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 6.32,
				"custom_tax_ctax_2" 	=> 3.21,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b55",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-3",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 205.47,
				"quantity" 				=> 1,
				"tax" 					=> 3.21,
				"discount" 				=> 4.21,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 12.25,
				"custom_tax_ctax_2" 	=> 6.66,
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(5899.55, $invoice->balance_due);
		$this->assertEquals(5899.55, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(0, $invoice->is_paid);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(87.77, $invoice->discount);
		$this->assertEquals(2, $invoice->discount_type);
		$this->assertEquals(87.77, $invoice->discount_amount_post_tax);
		$this->assertEquals(296.43, $invoice->discount_amount_pre_tax);
		$this->assertEquals(5361.01, $invoice->subtotal);
		$this->assertEquals(922.73, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($json_string, true));

		$items = InvoiceItem::all();

		$item1 = $items[0];
		$item2 = $items[1];
		$item3 = $items[2];

		$this->assertEquals('bla-123', $item1->row_uuid);
		$this->assertEquals($invoice->id, $item1->invoice_id);
		$this->assertEquals(1, $item1->product_id);
		$this->assertEquals(10, $item1->unit_price);
		$this->assertEquals(500, $item1->quantity);
		$this->assertEquals(5.60, $item1->discount);
		$this->assertEquals(280, $item1->discount_amount);
		$this->assertEquals(9, $item1->tax);
		$this->assertEquals(849.60, $item1->tax_amount);
		$this->assertEquals(5569.60, $item1->line_total);
		$this->assertEquals(4720, $item1->line_subtotal);

		
		$this->assertEquals('bla-123-2', $item2->row_uuid);
		$this->assertEquals($invoice->id, $item2->invoice_id);
		$this->assertEquals(1, $item2->product_id);
		$this->assertEquals(77.77, $item2->unit_price);
		$this->assertEquals(2, $item2->quantity);
		$this->assertEquals(5, $item2->discount);
		$this->assertEquals(7.78, $item2->discount_amount);
		$this->assertEquals(10.50, $item2->tax);
		$this->assertEquals(29.60, $item2->tax_amount);
		$this->assertEquals(177.36, $item2->line_total);
		$this->assertEquals(147.76, $item2->line_subtotal);

		$this->assertEquals('bla-123-3', $item3->row_uuid);
		$this->assertEquals($invoice->id, $item3->invoice_id);
		$this->assertEquals(1, $item3->product_id);
		$this->assertEquals(205.47, $item3->unit_price);
		$this->assertEquals(1, $item3->quantity);
		$this->assertEquals(4.21, $item3->discount);
		$this->assertEquals(8.65, $item3->discount_amount);
		$this->assertEquals(3.21, $item3->tax);
		$this->assertEquals(43.54, $item3->tax_amount);
		$this->assertEquals(240.36, $item3->line_total);
		$this->assertEquals(196.82, $item3->line_subtotal);


	}

	public function test_if_invoice_calculations_work_10_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.global_discount', 50);
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');


		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::insert([
			[
				'company_id'	=>	$company_id,
				'label'			=>	'c field',
				'type'			=>	'normal',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 1',
				'type'			=>	'tax',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 2',
				'type'			=>	'tax',
				'tax_rate'		=>	'0'
			]
		]);
		
		$json_string = '[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]';

		SettingsSection::insert([
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	$json_string
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 10,
				"quantity" 				=> 500,
				"tax" 					=> 9,
				"discount" 				=> 5.60,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 9,
				"custom_tax_ctax_2" 	=> 0,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b39",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-2",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 77.77,
				"quantity" 				=> 2,
				"tax" 					=> 10.50,
				"discount" 				=> 5,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 6.32,
				"custom_tax_ctax_2" 	=> 3.21,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b55",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-3",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 205.47,
				"quantity" 				=> 1,
				"tax" 					=> 3.21,
				"discount" 				=> 4.21,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 12.25,
				"custom_tax_ctax_2" 	=> 6.66,
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(2993.66, $invoice->balance_due);
		$this->assertEquals(2993.66, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(0, $invoice->is_paid);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(50, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(2993.66, $invoice->discount_amount_post_tax);
		$this->assertEquals(296.43, $invoice->discount_amount_pre_tax);
		$this->assertEquals(5361.01, $invoice->subtotal);
		$this->assertEquals(922.73, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($json_string, true));

		$items = InvoiceItem::all();

		$item1 = $items[0];
		$item2 = $items[1];
		$item3 = $items[2];

		$this->assertEquals('bla-123', $item1->row_uuid);
		$this->assertEquals($invoice->id, $item1->invoice_id);
		$this->assertEquals(1, $item1->product_id);
		$this->assertEquals(10, $item1->unit_price);
		$this->assertEquals(500, $item1->quantity);
		$this->assertEquals(5.60, $item1->discount);
		$this->assertEquals(280, $item1->discount_amount);
		$this->assertEquals(9, $item1->tax);
		$this->assertEquals(849.60, $item1->tax_amount);
		$this->assertEquals(5569.60, $item1->line_total);
		$this->assertEquals(4720, $item1->line_subtotal);

		
		$this->assertEquals('bla-123-2', $item2->row_uuid);
		$this->assertEquals($invoice->id, $item2->invoice_id);
		$this->assertEquals(1, $item2->product_id);
		$this->assertEquals(77.77, $item2->unit_price);
		$this->assertEquals(2, $item2->quantity);
		$this->assertEquals(5, $item2->discount);
		$this->assertEquals(7.78, $item2->discount_amount);
		$this->assertEquals(10.50, $item2->tax);
		$this->assertEquals(29.60, $item2->tax_amount);
		$this->assertEquals(177.36, $item2->line_total);
		$this->assertEquals(147.76, $item2->line_subtotal);

		$this->assertEquals('bla-123-3', $item3->row_uuid);
		$this->assertEquals($invoice->id, $item3->invoice_id);
		$this->assertEquals(1, $item3->product_id);
		$this->assertEquals(205.47, $item3->unit_price);
		$this->assertEquals(1, $item3->quantity);
		$this->assertEquals(4.21, $item3->discount);
		$this->assertEquals(8.65, $item3->discount_amount);
		$this->assertEquals(3.21, $item3->tax);
		$this->assertEquals(43.54, $item3->tax_amount);
		$this->assertEquals(240.36, $item3->line_total);
		$this->assertEquals(196.82, $item3->line_subtotal);


	}

	public function test_if_invoice_calculations_work_11_ict(){ 
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.global_discount', 8.67);
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');


		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);

		AdditionalProductColumnsField::insert([
			[
				'company_id'	=>	$company_id,
				'label'			=>	'c field',
				'type'			=>	'normal',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 1',
				'type'			=>	'tax',
				'tax_rate'		=>	'5.5'
			],
			[
				'company_id'	=>	$company_id,
				'label'			=>	'ctax 2',
				'type'			=>	'tax',
				'tax_rate'		=>	'0'
			]
		]);
		
		$json_string = '[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]';

		SettingsSection::insert([
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	$json_string
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 10,
				"quantity" 				=> 500,
				"tax" 					=> 9,
				"discount" 				=> 5.60,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 9,
				"custom_tax_ctax_2" 	=> 0,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b39",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-2",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 77.77,
				"quantity" 				=> 2,
				"tax" 					=> 10.50,
				"discount" 				=> 5,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 6.32,
				"custom_tax_ctax_2" 	=> 3.21,
			],
			[
				"id"		 			=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b55",
				"row_index" 			=>  0,
				"row_uuid" 				=> "bla-123-3",
				"line_subtotal" 		=> '  ',
				"tax_amount"	 		=> '  ',
				"line_total" 			=> "16.33",
				"product_id" 			=>  1,
				"item" 					=> "prod 3",
				"description" 			=> "prod 3 desc",
				"unit_price" 			=> 205.47,
				"quantity" 				=> 1,
				"tax" 					=> 3.21,
				"discount" 				=> 4.21,
				"normal_c_field" 		=> "555",
				"custom_tax_ctax_1" 	=> 12.25,
				"custom_tax_ctax_2" 	=> 6.66,
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$invoice = Invoice::first();
		
		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(5468.22, $invoice->balance_due);
		$this->assertEquals(5468.22, $invoice->total);
		$this->assertEquals(1, $invoice->pattern_matched);
		$this->assertEquals(0, $invoice->is_paid);
		$this->assertEquals(1, $invoice->company_id);
		$this->assertEquals(3, $invoice->scan_chars);
		$this->assertEquals(1, $invoice->client_id);
		$this->assertEquals(8.67, $invoice->discount);
		$this->assertEquals(1, $invoice->discount_type);
		$this->assertEquals(519.10, $invoice->discount_amount_post_tax);
		$this->assertEquals(296.43, $invoice->discount_amount_pre_tax);
		$this->assertEquals(5361.01, $invoice->subtotal);
		$this->assertEquals(922.73, $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, $invoice->payment_method);
	
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($json_string, true));

		$items = InvoiceItem::all();

		$item1 = $items[0];
		$item2 = $items[1];
		$item3 = $items[2];

		$this->assertEquals('bla-123', $item1->row_uuid);
		$this->assertEquals($invoice->id, $item1->invoice_id);
		$this->assertEquals(1, $item1->product_id);
		$this->assertEquals(10, $item1->unit_price);
		$this->assertEquals(500, $item1->quantity);
		$this->assertEquals(5.60, $item1->discount);
		$this->assertEquals(280, $item1->discount_amount);
		$this->assertEquals(9, $item1->tax);
		$this->assertEquals(849.60, $item1->tax_amount);
		$this->assertEquals(5569.60, $item1->line_total);
		$this->assertEquals(4720, $item1->line_subtotal);

		
		$this->assertEquals('bla-123-2', $item2->row_uuid);
		$this->assertEquals($invoice->id, $item2->invoice_id);
		$this->assertEquals(1, $item2->product_id);
		$this->assertEquals(77.77, $item2->unit_price);
		$this->assertEquals(2, $item2->quantity);
		$this->assertEquals(5, $item2->discount);
		$this->assertEquals(7.78, $item2->discount_amount);
		$this->assertEquals(10.50, $item2->tax);
		$this->assertEquals(29.60, $item2->tax_amount);
		$this->assertEquals(177.36, $item2->line_total);
		$this->assertEquals(147.76, $item2->line_subtotal);

		$this->assertEquals('bla-123-3', $item3->row_uuid);
		$this->assertEquals($invoice->id, $item3->invoice_id);
		$this->assertEquals(1, $item3->product_id);
		$this->assertEquals(205.47, $item3->unit_price);
		$this->assertEquals(1, $item3->quantity);
		$this->assertEquals(4.21, $item3->discount);
		$this->assertEquals(8.65, $item3->discount_amount);
		$this->assertEquals(3.21, $item3->tax);
		$this->assertEquals(43.54, $item3->tax_amount);
		$this->assertEquals(240.36, $item3->line_total);
		$this->assertEquals(196.82, $item3->line_subtotal);


	}

}
