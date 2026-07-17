<?php

namespace Tests\Feature;

use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\AdditionalProductColumnsField;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Models\SettingsSection;
use App\Models\Transaction;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\TransactionStatus;
use App\Traits\SettingsDefault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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

class InvoiceControllerAdditionalTest extends TestCase
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

	public function insertInvoice() : array {
		
		Bus::fake()->except([
			GenerateInvoiceJob::class
		]);
		Mail::fake();
		Storage::fake(INVOICES_DISK);

		$files = Storage::disk(INVOICES_DISK)->allFiles();
		Storage::disk(INVOICES_DISK)->delete($files);

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);

		$client->e_invoice_enabled = 1;
		$client->save();

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

		SettingsSection::insert([
			'id' => 100,
			'company_id' => $company_id,
			'type' => ISC_INVOICE_NUMBER_RESET_TYPE,
			'settings_json' => '{"reset": 1}',
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

		$data['custom_fields'] = $custom_fields_post;
		$data['timezone_offset_minutes'] = 330;
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_NETBANKING,
			'send_invoice_in_email'	=>	false,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		
		return ['invoice' => Invoice::find(1), 'client' => $client, 'data' => $data, 'c' => $c, 'company_id' => $company_id, 'device' => $device];

	}

	public function test_if_invoice_can_be_deleted_1_icat(){

		$inserted = $this->insertInvoice();

		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$invoice = Invoice::where('id', '=', 1)->first();
		$invoice->status = InvoiceStatus::CANCELLED->value;
		$invoice->save();

		$response = $this->delete('/api/manage-invoices', ['company_id' => $company_id, 'ids' => [1]], $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals('unable_to_delete_cancelled', $json['validity']);

	}

	public function test_if_invoice_can_be_deleted_2_icat(){

		$inserted = $this->insertInvoice();

		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$invoice = Invoice::where('id', '=', 1)->first();
		$invoice->status = InvoiceStatus::PAID->value;
		$invoice->save();

		$t = new Transaction();
		$t->company_id = $company_id;
		$t->invoice_id = 1;
		$t->amount = 1;
		$t->gateway_fees_amount = 1;
		$t->received_amount = 1;
		$t->payment_method = 1;
		$t->mode = 1;
		$t->token_id_identifier = 1;
		$t->is_approved = 1;
		$t->status = TransactionStatus::COMPLETED->value;
		$t->is_echeck = 0;
		$t->paid_at = now();
		$t->is_payment_captured = 1;
		$t->save();

		$response = $this->delete('/api/manage-invoices', ['company_id' => $company_id, 'ids' => [1]], $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals('unable_to_delete_payment_attached', $json['validity']);

	}

	public function test_if_invoice_can_be_deleted_3_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$response = $this->delete('/api/manage-invoices', ['company_id' => $company_id, 'ids' => [1]], $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('delete_success', $json['validity']);

	}

	public function test_if_invoice_can_be_sent_4_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$response = $this->get('/api/manage-invoices/send-invoice?company_id='.$company_id,  $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals(1, (int) count($json['errors']['invoice_id']));

	}

	public function test_if_invoice_can_be_sent_5_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$response = $this->get('/api/manage-invoices/send-invoice?company_id='.$company_id.'&invoice_id=10',  $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals(1, (int) count($json['errors']['invoice_id']));

	}

	public function test_if_invoice_can_be_sent_6_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$response = $this->get('/api/manage-invoices/send-invoice?company_id='.$company_id.'&invoice_id=1',  $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		Bus::assertDispatched(SendInvoiceEmailJob::class);

	}

	public function test_if_invoice_can_be_downloaded_7_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$response = $this->get('/api/manage-invoices/download-pdf?company_id='.$company_id.'&invoice_id=10',  $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		$this->assertEquals(1, (int) count($json['errors']['invoice_id']));
	}

	public function test_if_invoice_can_be_downloaded_8_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$response = $this->get('/api/manage-invoices/download-pdf?company_id='.$company_id.'&invoice_id=1',  $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertNotEmpty($json['url']);

	}

	public function test_if_invoice_can_be_downloaded_9_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$this->withoutExceptionHandling();
		$this->expectException(\Illuminate\Routing\Exceptions\InvalidSignatureException::class);
		$response = $this->get('/api/invoice/download?company_id='.$company_id.'&invoice_id=10',  $c['headers']);
		
	}

	public function test_if_invoice_can_be_downloaded_10_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$response = $this->get('/api/manage-invoices/download-pdf?company_id='.$company_id.'&invoice_id=1',  $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertNotEmpty($json['url']);
		
		$response = $this->get($json['url'],  $c['headers']);
		$response->assertStatus(200);
		
		$response->assertHeader('content-type', 'application/pdf');
		$response->assertHeader('content-disposition');

		$content_disposition = $response->headers->get('content-disposition');
		$this->assertStringContainsString('attachment; filename=', $content_disposition);
		
		$content_length = (int) $response->headers->get('content-length');
		$this->assertGreaterThan(1000, $content_length);
		
	}

	public function test_if_invoice_can_be_toggle_cancelled_11_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$patch_data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	50,
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $patch_data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals(2, (int) count($json['errors']));
		
		
	}

	public function test_if_invoice_can_be_toggle_cancelled_12_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$patch_data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1,
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $patch_data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals(1, (int) count($json['errors']));
		
		
	}

	public function test_if_invoice_can_be_toggle_cancelled_13_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$invoice->status = InvoiceStatus::PAID->value;
		$invoice->save();

		$patch_data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1,
			'status'		=>	InvoiceStatus::PENDING->value
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $patch_data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals('status_change_blocked', $json['validity']);
		
	}

	public function test_if_invoice_can_be_toggle_cancelled_14_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$invoice->status = InvoiceStatus::PARTIALLY_PAID->value;
		$invoice->save();

		$patch_data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1,
			'status'		=>	InvoiceStatus::PENDING->value
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $patch_data, $c['headers']);
		$response->assertStatus((int) config('global.error_code'));
		$json = $response->json();
		
		$this->assertEquals('status_change_blocked', $json['validity']);
		
	}

	public function test_if_invoice_can_be_toggle_cancelled_15_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$invoice->status = InvoiceStatus::PENDING->value;
		$invoice->save();

		$patch_data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1,
			'status'		=>	InvoiceStatus::PENDING->value
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $patch_data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('status_change_success', $json['validity']);

		$new_invoice = Invoice::where('id', '=', 1)->first();
		$this->assertEquals(InvoiceStatus::PENDING->value, (int) $new_invoice->status);
		
	}

	public function test_if_invoice_can_be_toggle_cancelled_16_icat(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$invoice->status = InvoiceStatus::CANCELLED->value;
		$invoice->save();

		$patch_data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1,
			'status'		=>	InvoiceStatus::PENDING->value
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $patch_data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('status_change_success', $json['validity']);

		$new_invoice = Invoice::where('id', '=', 1)->first();
		$this->assertEquals(InvoiceStatus::PENDING->value, (int) $new_invoice->status);
		
	}

	public function test_if_invoice_can_be_toggle_cancelled_17_icat(){

		$inserted = $this->insertInvoice();
		Bus::fake();
		Mail::fake();
		Storage::fake(INVOICES_DISK);
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		$client = $inserted['client'];
		$c = $inserted['c'];

		$invoice->status = InvoiceStatus::PENDING->value;
		$invoice->save();

		$patch_data = [
			'company_id'	=>	$company_id,
			'invoice_id'	=>	1,
			'status'		=>	InvoiceStatus::CANCELLED->value
		];

		$response = $this->patch('/api/manage-invoices/toggle-cancel', $patch_data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('status_change_success', $json['validity']);

		$new_invoice = Invoice::where('id', '=', 1)->first();
		$this->assertEquals(InvoiceStatus::CANCELLED->value, (int) $new_invoice->status);
		
		Bus::assertDispatched(GenerateInvoiceJob::class);
	}


}
