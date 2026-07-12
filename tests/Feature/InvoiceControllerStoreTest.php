<?php

namespace Tests\Feature;

use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendEmailJob;
use App\Mail\SendInvoice;
use App\Models\AdditionalProductColumnsField;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Models\SettingsSection;
use App\Services\Invoice\InvoiceService;
use App\Traits\SettingsDefault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Queue\Queue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\GeneralFunctions;
use Tests\Traits\SetAccess;

class InvoiceControllerStoreTest extends TestCase
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

	public function test_if_invoice_saves_with_default_product_rows_no_custom_fields_and_required_fields_1_icst(){ 
		
		Bus::fake();

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		//$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		//$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		//$custom_fields_post = $temp['fields'];

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

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	true,
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
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		Bus::assertDispatched(GenerateInvoiceJob::class);


	}

	public function test_if_invoice_saves_with_default_product_rows_no_custom_fields_and_required_fields_2_icst(){ 
		
		

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		//$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		//$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		//$custom_fields_post = $temp['fields'];

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

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	true,
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
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		
		Bus::fake([SendEmailJob::class]);
		$job = new GenerateInvoiceJob($company_id, $invoice->id, 330, app(InvoiceService::class));
		$job->handle();
		Bus::assertDispatched(SendEmailJob::class);


	}

	public function test_if_invoice_saves_with_default_product_rows_no_custom_fields_and_required_fields_3_icst(){ 
		
		Storage::fake(INVOICES_DISK);

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		//$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		//$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		//$custom_fields_post = $temp['fields'];

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
				"discount" 			=> 0,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_CASH,
			'send_invoice_in_email'	=>	true,
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
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));


		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(1, $files);


	}

	public function test_if_invoice_saves_with_default_product_rows_no_custom_fields_and_required_fields_4_icst(){ 
		
		Storage::fake(INVOICES_DISK);

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"discount" 			=> 0,
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
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));


		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(1, $files);


	}

	public function test_if_invoice_saves_with_default_product_rows_no_custom_fields_and_required_fields_5_icst(){ 
		
		Bus::fake();

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
				"unit_price" 		=> 15.55,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"discount" 			=> 0,
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
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_CASH, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		Bus::assertNotDispatched(GenerateInvoiceJob::class);


	}


	
	public function test_if_invoice_saves_with_default_product_rows_no_custom_fields_and_required_fields_6_icst(){ 

		Storage::fake(INVOICES_DISK);
		
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

		$paypal_settings = json_encode([
			'mode'			=>	'sandbox',
			'app_id'		=>	env('TEST_PAYPAL_APP_ID'),
			'secret'		=>	encrypt(env('TEST_PAYPAL_SECRET_KEY')),
			'client_id'		=>	env('TEST_PAYPAL_CLIENT_ID'),
			'webhook_id'	=>	env('TEST_PAYPAL_WEBHOOK_ID'),
		]);

		SettingsSection::insert([
			'id'	=>	1,
			'company_id'	=>	$company_id,
			'type'	=>	PAYMENTS_PAYPAL_TYPE,
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
				"discount" 			=> 0,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_PAYPAL,
			'send_invoice_in_email'	=>	true,
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
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_PAYPAL, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(1, $files);

	}

	public function test_if_invoice_saves_with_default_product_rows_no_custom_fields_and_required_fields_7_icst(){ 

		Storage::fake(INVOICES_DISK);
		
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
				"discount" 			=> 0,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_STRIPE,
			'send_invoice_in_email'	=>	true,
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
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_STRIPE, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(1, $files);

	}

	public function test_if_invoice_saves_with_default_product_rows_with_custom_fields_and_required_fields_8_icst(){ 

		Storage::fake(INVOICES_DISK);
		
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
			'send_invoice_in_email'	=>	true,
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
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_NETBANKING, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(1, $files);

	}

	public function test_if_invoice_saves_with_default_product_rows_with_custom_fields_and_required_fields_9_icst(){ 

		Storage::fake(INVOICES_DISK);
		
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
				"discount" 			=> 0,
				"tax" 				=> 5,
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

		$default_product_rows_settings = json_encode($this->getDefaultProductColumnsSettings($company_id)['rows']);

		$invoice = Invoice::first();

		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('', $invoice->po_number);
		$this->assertEquals(16.33, (float) $invoice->balance_due);
		$this->assertEquals(16.33, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(0, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_NETBANKING, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		$files = Storage::disk(INVOICES_DISK)->allFiles();
		Storage::disk(INVOICES_DISK)->delete($files);
		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(0, $files);

	}

	public function test_if_invoice_saves_with_default_product_rows_with_custom_fields_and_all_fields_10_icst(){ 

		Storage::fake(INVOICES_DISK);
		
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
		Arr::set($data, 'data.invoice_details.po_number', 'po number here');
		Arr::set($data, 'data.invoice_details.global_discount', '5');
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');
		Arr::set($data, 'data.invoice_terms', 'invoice terms here');

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
				"discount" 			=> 0,
				"tax" 				=> 5,
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

		$default_product_rows_settings = json_encode($this->getDefaultProductColumnsSettings($company_id)['rows']);

		$invoice = Invoice::first();

		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('po number here', $invoice->po_number);
		$this->assertEquals(15.51, (float) $invoice->balance_due);
		$this->assertEquals(15.51, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(5, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('invoice terms here', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_NETBANKING, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(0, $files);

		//test for product rows.
		$invoice_items = InvoiceItem::all();

		$this->assertEquals(1, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(15.55, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(0.78, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(16.33, (float) $invoice_items[0]->line_total);
		$this->assertEquals(15.55, (float) $invoice_items[0]->line_subtotal);


	}

	public function test_if_invoice_saves_with_default_product_rows_without_custom_fields_and_all_fields_11_icst(){ 

		Storage::fake(INVOICES_DISK);
		$files = Storage::disk(INVOICES_DISK)->allFiles();
		Storage::disk(INVOICES_DISK)->delete($files);
		
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
		Arr::set($data, 'data.invoice_details.po_number', 'po number here');
		Arr::set($data, 'data.invoice_details.global_discount', '5');
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');
		Arr::set($data, 'data.invoice_terms', 'invoice terms here');

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
				"discount" 			=> 0,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555"
			]
		];

		$data['custom_fields'] = [];
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
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
		$this->assertEquals('po number here', $invoice->po_number);
		$this->assertEquals(15.51, (float) $invoice->balance_due);
		$this->assertEquals(15.51, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(5, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(0.78, (float) $invoice->tax_amount);
		$this->assertEquals('invoice terms here', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_NETBANKING, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		
		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(0, $files);

		//test for product rows.
		$invoice_items = InvoiceItem::all();

		$this->assertEquals(1, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(15.55, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(0.78, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(16.33, (float) $invoice_items[0]->line_total);
		$this->assertEquals(15.55, (float) $invoice_items[0]->line_subtotal);


	}

	public function test_if_invoice_saves_with_custom_product_rows_without_custom_fields_and_all_fields_12_icst(){ 

		Storage::fake(INVOICES_DISK);
		$files = Storage::disk(INVOICES_DISK)->allFiles();
		Storage::disk(INVOICES_DISK)->delete($files);

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
		Arr::set($data, 'data.invoice_details.po_number', 'po number here');
		Arr::set($data, 'data.invoice_details.global_discount', '5');
		Arr::set($data, 'data.invoice_details.global_discount_type', 'percentage');
		Arr::set($data, 'data.invoice_terms', 'invoice terms here');

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
			'id' => 2,
			'company_id'	=>	$company_id,
			'type'	=> ISC_PRODUCT_COLUMNS_TYPE,
			'settings_json'	=>	'[{"id": 1, "tax": false, "text": "Item", "type": "normal", "value": "item", "mapped": ["product_id"]}, {"id": 2, "tax": false, "text": "Description", "type": "normal", "value": "description", "mapped": ["description"]}, {"id": 3, "tax": false, "text": "Unit cost", "type": "normal", "value": "unit_cost", "mapped": ["unit_price"]}, {"id": 4, "tax": false, "text": "Quantity", "type": "normal", "value": "quantity", "mapped": ["quantity"]}, {"id": 6, "tax": true, "text": "Tax", "type": "normal", "value": "tax", "mapped": ["tax"], "tax_rate": 0}, {"id": 13, "tax": false, "text": "c field", "type": "custom", "value": "c field", "mapped": "", "tax_rate": 0, "id_column": 1}, {"id": 7, "tax": false, "text": "Line total", "type": "normal", "value": "line_total", "mapped": ["line_total"]}, {"id": 12, "tax": true, "text": "ctax 1", "type": "custom", "value": "ctax 1", "mapped": "", "tax_rate": 5, "id_column": 2}, {"id": 13, "tax": true, "text": "ctax 2", "type": "custom", "value": "ctax 2", "mapped": "", "tax_rate": 2.5, "id_column": 3}]'
			
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
		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('invoice_created', $json['validity']);

		$default_product_rows_settings = SettingsSection::where([['id', '=', 2], ['company_id', '=', $company_id], ['type', '=', ISC_PRODUCT_COLUMNS_TYPE]])->first();
		$default_product_rows_settings = $default_product_rows_settings->settings_json;

		$invoice = Invoice::first();

		$this->assertEquals('123', $invoice->invoice_number);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->invoice_date);
		$this->assertEquals('2026-06-23 14:32:47', $invoice->due_date);
		$this->assertEquals('po number here', $invoice->po_number);
		$this->assertEquals(17.43, (float) $invoice->balance_due);
		$this->assertEquals(17.43, (float) $invoice->total);
		$this->assertEquals(1, (int) $invoice->pattern_matched);
		$this->assertEquals(1, (int) $invoice->company_id);
		$this->assertEquals(3, (int) $invoice->scan_chars);
		$this->assertEquals(1, (int) $invoice->client_id);
		$this->assertEquals(5, (int) $invoice->discount);
		$this->assertEquals(1, (int) $invoice->discount_type);
		$this->assertEquals(0, (int) $invoice->discount_amount);
		$this->assertEquals(15.55, (float) $invoice->subtotal);
		$this->assertEquals(2.80, (float) $invoice->tax_amount);
		$this->assertEquals('invoice terms here', $invoice->invoice_terms);
		$this->assertEquals(PAYMENT_NETBANKING, (int) $invoice->payment_method);
		$this->assertJsonWithoutIds(json_decode($invoice->settings_snapshot, true), json_decode($default_product_rows_settings, true));

		
		$files = Storage::disk(INVOICES_DISK)->allFiles((string) $invoice->id);
		$this->assertCount(0, $files);

		//test for product rows.
		$invoice_items = InvoiceItem::all();

		$this->assertEquals(1, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(15.55, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(2.80, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(18.35, (float) $invoice_items[0]->line_total);
		$this->assertEquals(15.55, (float) $invoice_items[0]->line_subtotal);


	}


}
