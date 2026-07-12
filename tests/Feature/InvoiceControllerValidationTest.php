<?php

namespace Tests\Feature;

use App\Models\AdditionalCompanyField;
use App\Models\AdditionalProductColumnsField;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Models\SettingsSection;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Traits\SettingsDefault;
use Database\Factories\AdditionalProductColumnsFieldFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceControllerValidationTest extends TestCase
{
    use SettingsDefault, RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	public function insertClient(int $company_id, mixed $headers, int $currency_id = 5) : Client {
		DB::table('clients')->truncate();
		
		$currency = Currency::where('id', '=', $currency_id)->first();
		$industry = Industry::inRandomOrder()->first();
		$country = Country::inRandomOrder()->first();
		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $headers);
		$response->assertStatus(200);
		return Client::first();
	}

	public function test_invoice_posting_without_custom_fields_invalid_tab0_data_1_icvt(){ //without product rows.

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$data = [];
		Arr::set($data, 'data.invoice_details.client.client_id', 555);

		$response = $this->post('/api/manage-invoices', $data, $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_request_company', $json['validity']);

	}

	public function test_invoice_posting_without_custom_fields_invalid_tab0_data_2_icvt(){ //without product rows.

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', 555);
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_request_tab0', $json['validity']);

	}

	public function test_invoice_posting_without_custom_fields_invalid_tab0_data_3_icvt(){ //without product rows.

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '   ');
		Arr::set($data, 'data.invoice_details.due_date.value', '');
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_request_tab0', $json['validity']);


	}

	public function test_invoice_posting_without_custom_fields_invalid_tab0_data_4_icvt(){ //without product rows.

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

		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_request_product_rows_tab0', $json['validity']);


	}

	//with invalid product rows. should fail because of no uuid
	public function test_invoice_posting_without_custom_fields_invalid_tab0_data_5_icvt(){ 

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


		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "  ",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  18,
				"item" 				=> "prod 3",
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		//dd($json);
		$this->assertEquals('invalid_product_uuid_tab0', $json['validity']);


	}

	//with invalid product rows. should fail because of no uuid for second row
	public function test_invoice_posting_without_custom_fields_invalid_tab0_data_6_icvt(){ 

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


		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  18,
				"item" 				=> "prod 3",
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			],
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  18,
				"item" 				=> "prod 3",
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		//dd($json);
		$this->assertEquals('invalid_product_uuid_tab0', $json['validity']);


	}

	//with invalid product rows. should fail because of no uuid for second row
	public function test_invoice_posting_without_custom_fields_invalid_tab0_data_7_icvt(){ 

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


		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  18,
				"item" 				=> "prod 3",
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			],
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  18,
				"item" 				=> "prod 3",
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		//dd($json);
		$this->assertEquals('invalid_product_uuid_tab0', $json['validity']);


	}

	//with invalid product rows. should fail because of no product in db
	public function test_invoice_posting_without_custom_fields_invalid_tab0_data_8_icvt(){ 

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


		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  18,
				"item" 				=> "prod 3",
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		//dd($json);
		$this->assertEquals('invalid_product_data_tab0', $json['validity']);


	}

	//valid data. should pass for tab0
	public function test_invoice_posting_without_custom_fields_valid_tab0_data_9_icvt(){ 

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
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_data_tab1', $json['validity']);


	}

	public function test_invoice_posting_without_custom_fields_valid_tab0_data_valid_tab1_10_icvt(){ 

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
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_request_tab2', $json['validity']);


	}

	public function test_invoice_posting_without_custom_fields_valid_tab0_data_invalid_tab1_11_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], 10,'invoice');


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
				"description" 		=>  "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_data_tab1', $json['validity']);


	}

	public function test_invoice_posting_without_custom_fields_valid_tab0_data_invalid_tab1_12_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(true, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_data_tab1', $json['validity']);


	}

	public function test_invoice_posting_without_custom_fields_valid_tab0_data_valid_tab1_13_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_request_tab2', $json['validity']);


	}

	public function test_invoice_posting_without_custom_fields_valid_tab1_data_invalid_tab2_14_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['settings'] = [];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_request_tab2', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tab1_data_invalid_tab2_15_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['settings'] = [
			'payment_method'	=>	''
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_request_tab2', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tab1_data_invalid_tab2_16_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
				"discount" 			=> 0,
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['settings'] = [
			'payment_method'		=>	'1',
			'send_invoice_in_email'	=>	true,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('invalid_timezone', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tab1_data_invalid_tab2_17_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"discount" 			=> 0,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tab1_data_valid_tab2_unsupported_currency_stripe_18_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 81);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"discount" 			=> 0,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();

		$this->assertEquals('unsupported_currency', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tab1_data_valid_tab2_unsupported_currency_paypal_19_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 81);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"discount" 			=> 0,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'3',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('unsupported_currency', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tabs_for_product_rows_integrity_19_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

		$data = [];
		Arr::set($data, 'company_id', $company_id);
		Arr::set($data, 'data.invoice_details.client.client_id', $client->id);
		Arr::set($data, 'data.invoice_details.invoice_date.value', '2026-06-23T14:32:47.853Z');
		Arr::set($data, 'data.invoice_details.invoice_number.value', '123');
		Arr::set($data, 'data.invoice_details.due_date.value', '2026-06-23T14:32:47.853Z');

		Product::factory()->count(5)->create([
			'company_id'	=>	$company_id
		]);
		SettingsSection::truncate();
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}]'
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tabs_for_product_rows_integrity_20_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
			'company_id'	=>	$company_id,
			'label'			=>	'c field',
			'type'			=>	'tax',
			'tax_rate'		=>	'5.5'
		]);
		SettingsSection::truncate();
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 5.5, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}]'
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"custom_tax_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tabs_for_product_rows_integrity_21_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
			'company_id'	=>	$company_id,
			'label'			=>	'c field',
			'type'			=>	'tax',
			'tax_rate'		=>	'5.5'
		]);
	
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ""}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 5.5, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}]'
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('something_went_wrong', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tabs_for_product_rows_integrity_22_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
			'company_id'	=>	$company_id,
			'label'			=>	'c field',
			'type'			=>	'tax',
			'tax_rate'		=>	'5.5'
		]);
	
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id2"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 5.5, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}]'
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('mismatch_fields', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tabs_for_product_rows_integrity_23_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
			'company_id'	=>	$company_id,
			'label'			=>	'c field',
			'type'			=>	'tax',
			'tax_rate'		=>	'5.5'
		]);
	
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 5.5}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}]'
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals('mismatch_fields', $json['validity']);


	}

	public function test_invoice_posting_with_custom_fields_valid_tabs_for_product_rows_integrity_24_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
		
	
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]'
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
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
				"custom_tax_ctax_2" 	=> "7",
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);


	}


	public function test_invoice_posting_with_custom_fields_valid_tabs_for_update_invoices_1_icvt(){ 

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
		
	
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]'
			
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
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
				"custom_tax_ctax_2" 	=> "7",
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		$this->assertEquals('invoice_created', $json['validity']);

		//update invoice status
		$invoice = Invoice::where('id', '=', 1)->first();
		$invoice->status = InvoiceStatus::PARTIALLY_PAID->value;
		$invoice->save();

		$response = $this->patch('/api/manage-invoices/1', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertEquals('invalid_payment_attached', $json['validity']);
		$this->assertEquals(2, (int) $json['tab_switch']);


		//update invoice status
		$invoice = Invoice::where('id', '=', 1)->first();
		$invoice->status = InvoiceStatus::PAID->value;
		$invoice->save();

		$response = $this->patch('/api/manage-invoices/1', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertEquals('invalid_payment_attached', $json['validity']);
		$this->assertEquals(2, (int) $json['tab_switch']);

		//update invoice status
		$invoice = Invoice::where('id', '=', 1)->first();
		$invoice->status = InvoiceStatus::PENDING->value;
		$invoice->save();

		$response = $this->patch('/api/manage-invoices/1', $data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		$this->assertEquals('invoice_created', $json['validity']);


		//update invoice status for cancelled
		$invoice = Invoice::where('id', '=', 1)->first();
		$invoice->status = InvoiceStatus::CANCELLED->value;
		$invoice->save();

		$response = $this->patch('/api/manage-invoices/1', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertEquals('invalid_invoice_cancelled', $json['validity']);
		$this->assertEquals(2, (int) $json['tab_switch']);


		//update invoice status for invalid payment gateway
		$data['settings']['payment_method'] = 560;
		//update invoice status for cancelled
		$invoice = Invoice::where('id', '=', 1)->first();
		$invoice->status = InvoiceStatus::PENDING->value;
		$invoice->save();
		$response = $this->patch('/api/manage-invoices/1', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertEquals('invalid_payment_gateway', $json['validity']);
		$this->assertEquals(2, (int) $json['tab_switch']);

		$response = $this->patch('/api/manage-invoices/100', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertEquals('invalid_data', $json['validity']);
		$this->assertEquals(0, (int) $json['tab_switch']);

	}

	public function test_for_initial_data_fetching_for_invoices_1_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertEquals('invalid_timezone', $json['validity']);

	}

	public function test_for_initial_data_fetching_for_invoices_2_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=456', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();

		$this->assertEquals(7, (int) count($json['product_columns']));
		$this->assertEquals(7, (int) count($json['total_fields']['rows']));
		$this->assertEquals(0, (int) count($json['total_fields']['dropdown']));
		$this->assertEquals(0, (int) count($json['custom_fields']));
		$this->assertEquals(2, (int) count($json['gateways']));
	}

	public function test_for_initial_data_fetching_for_invoices_3_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$settings_section = new SettingsSection();
		$settings_section->company_id = $company_id;
		$settings_section->type = PAYMENTS_PAYPAL_TYPE;
		$settings_section->settings_json = json_encode(['whatever']);
		$settings_section->save();

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=456', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals(7, (int) count($json['product_columns']));
		$this->assertEquals(7, (int) count($json['total_fields']['rows']));
		$this->assertEquals(0, (int) count($json['total_fields']['dropdown']));
		$this->assertEquals(0, (int) count($json['custom_fields']));
		$this->assertEquals(3, (int) count($json['gateways']));
		$this->assertEquals('PayPal', $json['gateways'][2]['text']);
	}

	public function test_for_initial_data_fetching_for_invoices_4_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$settings_section = new SettingsSection();
		$settings_section->company_id = $company_id;
		$settings_section->type = PAYMENTS_PAYPAL_TYPE;
		$settings_section->settings_json = json_encode(['whatever']);
		$settings_section->save();

		$settings_section = new SettingsSection();
		$settings_section->company_id = $company_id;
		$settings_section->type = PAYMENTS_STRIPE_TYPE;
		$settings_section->settings_json = json_encode(['whatever']);
		$settings_section->save();

		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=456', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals(7, (int) count($json['product_columns']));
		$this->assertEquals(7, (int) count($json['total_fields']['rows']));
		$this->assertEquals(0, (int) count($json['total_fields']['dropdown']));
		$this->assertEquals(0, (int) count($json['custom_fields']));
		$this->assertEquals(4, (int) count($json['gateways']));
		$this->assertEquals('PayPal', $json['gateways'][2]['text']);
		$this->assertEquals('Stripe', $json['gateways'][3]['text']);
	}

	public function test_for_initial_data_fetching_for_invoices_5_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$settings_section = new SettingsSection();
		$settings_section->company_id = $company_id;
		$settings_section->type = PAYMENTS_PAYPAL_TYPE;
		$settings_section->settings_json = json_encode(['whatever']);
		$settings_section->save();

		$settings_section = new SettingsSection();
		$settings_section->company_id = $company_id;
		$settings_section->type = PAYMENTS_STRIPE_TYPE;
		$settings_section->settings_json = json_encode(['whatever']);
		$settings_section->save();
		
		$total_fields = '[{"id": 6933044056, "text": "Subtotal", "type": "normal", "value": "subtotal", "mapped": ["subtotal"]}, {"id": 6127059566, "text": "Discount Post Tax", "type": "normal", "value": "discount_post_tax", "mapped": ["discount_amount_post_tax"]}, {"id": 3378265136, "text": "Discount Pre tax", "type": "normal", "value": "discount_pre_tax", "mapped": ["discount_amount_pre_tax"]}, {"id": 16667257696, "text": "Total Taxes", "type": "normal", "value": "total_taxes", "mapped": ["tax_amount"]}, {"id": 5146167046, "text": "Total", "type": "normal", "value": "total", "mapped": ["total"]}, {"id": 8811624616, "text": "Paid to Date", "type": "normal", "value": "paid_to_date", "mapped": ""}]';
		$settings_section = new SettingsSection();
		$settings_section->company_id = $company_id;
		$settings_section->type = ISC_INVOICE_TOTAL_FIELDS_TYPE;
		$settings_section->settings_json = $total_fields;
		$settings_section->save();

		$column_fields = '[{"id": 4897526646, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 18206891596, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 9133237166, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 10771190006, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 19969935766, "text": "Discount %", "type": "normal", "value": "discount", "mapped": ["discount"]}, {"id": 20958759086, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 1782808280837, "tax": true, "text": "w tax 1", "type": "custom", "value": "w tax 1", "mapped": "", "tax_rate": 5, "id_column": 22}, {"id": 1782808282033, "tax": true, "text": "w tax 2", "type": "custom", "value": "w tax 2", "mapped": "", "tax_rate": 3, "id_column": 23}, {"id": 20501403416, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}]';
		$settings_section = new SettingsSection();
		$settings_section->company_id = $company_id;
		$settings_section->type = ISC_PRODUCT_COLUMNS_TYPE;
		$settings_section->settings_json = $column_fields;
		$settings_section->save();

		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		
		$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=456', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals(4, (int) count($json['gateways']));
		$this->assertEquals('PayPal', $json['gateways'][2]['text']);
		$this->assertEquals('Stripe', $json['gateways'][3]['text']);
		$this->assertEquals(10, (int) count($json['custom_fields']));
		$this->assertEquals(6, (int) count($json['total_fields']));
		$this->assertEquals(9, (int) count($json['product_columns']));
	}

	public function test_for_products_fetching_for_invoices_5_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->get('/api/manage-invoices/fetch-products?company_id='.$company_id, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertEquals(1, (int) count($json['errors']['searched']));
		
	}

	public function test_for_products_fetching_for_invoices_6_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$prod = new Product();
		$prod->company_id = $company_id;
		$prod->product_name = 'product name here';
		$prod->price = 11.99;
		$prod->sku = 'test';
		$prod->description = 'test description';
		$prod->save();

		$response = $this->get('/api/manage-invoices/fetch-products?company_id='.$company_id.'&searched=prod', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		$this->assertEquals(1, (int) count($json));

		$response = $this->get('/api/manage-invoices/fetch-products?company_id='.$company_id.'&searched=whatever', $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		$this->assertEquals(0, (int) count($json));
	}

	public function test_for_validating_index_fetching_invoices_6_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->get('/api/manage-invoices?company_id='.$company_id, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$response = $this->get('/api/manage-invoices?company_id='.$company_id.'&default_per_page=2', $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		$this->assertEquals(9, (int) count($json['table_data']['columns']));
		
	}

	public function test_for_validating_fetching_single_invoice_6_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
			'company_id'	=>	$company_id,
			'label'			=>	'c field',
			'type'			=>	'tax',
			'tax_rate'		=>	'5.5'
		]);
		SettingsSection::truncate();
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 5.5, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}]'
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"custom_tax_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$response = $this->get('/api/manage-invoices/1/?company_id='.$company_id, $c['headers']);
		$response->assertStatus(200);
		
	}

	public function test_for_deleting_invoices_7_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();


		$response = $this->delete('/api/manage-invoices?company_id='.$company_id, [], $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertEquals('invalid_ids', $json['validity']);
		
	}

	public function test_for_deleting_invoices_8_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();


		$response = $this->delete('/api/manage-invoices?company_id='.$company_id, ['ids' => ['something']], $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertEquals('non_numeric', $json['validity']);
		
	}

	public function test_for_deleting_invoices_9_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();


		$response = $this->delete('/api/manage-invoices?company_id='.$company_id, ['ids' => [1]], $c['headers']);
		$response->assertStatus(200);

		$json = $response->json();
		$this->assertEquals('delete_success', $json['validity']);
		
	}

	public function test_for_sending_invoices_10_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();


		$response = $this->get('/api/manage-invoices/send-invoice?company_id='.$company_id, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertEquals(1, (int) count($json['errors']['invoice_id']));
		
	}

	public function test_for_sending_invoices_11_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();


		$response = $this->get('/api/manage-invoices/send-invoice?company_id='.$company_id.'&invoice_id=500', $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertEquals(1, (int) count($json['errors']['invoice_id']));
		
	}

	public function test_for_sending_invoices_12_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
			'company_id'	=>	$company_id,
			'label'			=>	'c field',
			'type'			=>	'tax',
			'tax_rate'		=>	'5.5'
		]);
		SettingsSection::truncate();
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 5.5, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}]'
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"custom_tax_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$invoice = Invoice::where('id', '=', 1)->first();
		$invoice->status = InvoiceStatus::CANCELLED->value;
		$invoice->save();

		$response = $this->get('/api/manage-invoices/send-invoice?company_id='.$company_id.'&invoice_id=1', $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertEquals('can_not_send_cancelled', $json['validity']);
		
	}

	public function test_for_download_invoice_12_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();


		$response = $this->get('/api/manage-invoices/download-pdf?company_id='.$company_id.'&invoice_id=500', $c['headers']);
		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertEquals(1, (int) count($json['errors']['invoice_id']));
		
	}

	public function test_for_serve_invoice_13_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$this->withoutExceptionHandling();
		$this->expectException(\Illuminate\Routing\Exceptions\InvalidSignatureException::class);
		$response = $this->get('/api/invoice/download?company_id='.$company_id.'&invoice_id=500', $c['headers']);
		$response->assertStatus(403);

		
	}

	public function test_for_toggle_cancel_invoice_14_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$data = [
			'company_id'	=>	$company_id
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals(1, (int) $json['errors']['invoice_id']);
		$this->assertEquals(1, (int) $json['errors']['status']);
		
	}

	public function test_for_toggle_cancel_invoice_12_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals(1, (int) $json['errors']['invoice_id']);
		$this->assertEquals(1, (int) $json['errors']['status']);
		
	}

	public function test_for_toggle_cancel_invoice_13_ivvt(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		$custom_fields_post = $temp['fields'];

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
			'company_id'	=>	$company_id,
			'label'			=>	'c field',
			'type'			=>	'tax',
			'tax_rate'		=>	'5.5'
		]);
		SettingsSection::truncate();
		SettingsSection::insert([
			'id' => 1,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 5.5, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}]'
		]);

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b30",
				"row_index" 		=>  0,
				"row_uuid" 			=> uniqid(time()),
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"custom_tax_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	'4',
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		$response->assertStatus(200);

		$data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1,
			'status'		=>	InvoiceStatus::PAID->value,
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals(1, (int) $json['errors']['status']);

		$data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1,
			'status'		=>	InvoiceStatus::PARTIALLY_PAID->value,
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals(1, (int) $json['errors']['status']);
		
	}



}
