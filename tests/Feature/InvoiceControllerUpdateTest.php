<?php

namespace Tests\Feature;

use App\Jobs\GenerateInvoiceJob;
use App\Models\AdditionalProductColumnsField;
use App\Models\AdditionalProductColumnsFieldValue;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoiceItem;
use App\Models\InvoicesCustomField;
use App\Models\InvoiceSnapshot;
use App\Models\Product;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\InvoiceGeneration\InvoiceSnapshot as Snapshot;
use App\Modules\Payment\Enums\InvoiceStatus;
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

class InvoiceControllerUpdateTest extends TestCase {

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
			'send_invoice_in_email'	=>	true,
		];
		
		$response = $this->post('/api/manage-invoices', $data, $c['headers']);
		
		return ['invoice' => Invoice::find(1), 'client' => $client, 'data' => $data, 'c' => $c, 'company_id' => $company_id, 'device' => $device];

	}

	public function test_if_invoice_updates_without_changes_1_icutt(){

		$inserted = $this->insertInvoice();

		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		$company_id = $inserted['company_id'];
		//$client = $inserted['client'];
		$c = $inserted['c'];

		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		$s->settings_json = json_encode(['reset' => 1]);
		$s->save();

		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);

		$new_invoice = Invoice::find(1);
		$old_invoice = Arr::except($invoice->getAttributes(), ['updated_at']);
		$new_invoice = Arr::except($new_invoice->getAttributes(), ['updated_at']);
		$this->assertEquals($old_invoice, $new_invoice);

		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(2, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(15.55, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(2.80, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(18.35, (float) $invoice_items[0]->line_total);
		$this->assertEquals(15.55, (float) $invoice_items[0]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(10, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(10, (int) $invoices_custom_field_values->count());

		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(1, (int) $s_json['reset']);

		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertNotEmpty($invoice->xml_file);

	}

	public function test_if_invoice_updates_with_changes_2_icutt(){

		$inserted = $this->insertInvoice();

		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		
		$company_id = $inserted['company_id'];
		//$client = $inserted['client'];
		$c = $inserted['c'];
		
		$data['data']['invoice_details']['po_number'] = '';
		$data['data']['invoice_details']['global_discount_type'] = '';
		$data['data']['invoice_details']['global_discount'] = '';
		$data['data']['invoice_terms'] = '';
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		$s->settings_json = json_encode(['reset' => 1]);
		$s->save();

		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);

		$new_invoice_bk = Invoice::find(1);
		$old_invoice = Arr::except($invoice->getAttributes(), ['updated_at', 'invoice_terms', 'balance_due', 'total', 'discount', 'discount_amount_post_tax', 'po_number']);
		$new_invoice = Arr::except($new_invoice_bk->getAttributes(), ['updated_at', 'invoice_terms', 'balance_due', 'total', 'discount', 'discount_amount_post_tax', 'po_number']);
		$this->assertEquals($old_invoice, $new_invoice);

		$this->assertEquals('', $new_invoice_bk->invoice_terms);
		$this->assertEquals('', $new_invoice_bk->po_number);
		$this->assertEquals('18.35', (string) $new_invoice_bk->balance_due);
		$this->assertEquals('18.35', (string) $new_invoice_bk->total);
		$this->assertEquals('0', (string) $new_invoice_bk->discount);
		$this->assertEquals('0', (string) $new_invoice_bk->discount_amount_post_tax);

		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(2, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(15.55, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(2.80, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(18.35, (float) $invoice_items[0]->line_total);
		$this->assertEquals(15.55, (float) $invoice_items[0]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(10, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(10, (int) $invoices_custom_field_values->count());
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(1, (int) $s_json['reset']);

		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertNotEmpty($invoice->xml_file);

	}


	public function test_if_invoice_updates_with_changes_3_icutt(){

		$inserted = $this->insertInvoice();

		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		
		$company_id = $inserted['company_id'];
		//$client = $inserted['client'];
		$c = $inserted['c'];
		
		$data['data']['invoice_details']['po_number'] = '';
		$data['data']['invoice_details']['global_discount_type'] = '';
		$data['data']['invoice_details']['global_discount'] = '';
		$data['data']['invoice_terms'] = '';
		InvoicesCustomField::truncate();
		$data['custom_fields'] = [];

		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);

		$new_invoice_bk = Invoice::find(1);
		$old_invoice = Arr::except($invoice->getAttributes(), ['updated_at', 'invoice_terms', 'balance_due', 'total', 'discount', 'discount_amount_post_tax', 'po_number']);
		$new_invoice = Arr::except($new_invoice_bk->getAttributes(), ['updated_at', 'invoice_terms', 'balance_due', 'total', 'discount', 'discount_amount_post_tax', 'po_number']);
		$this->assertEquals($old_invoice, $new_invoice);

		$this->assertEquals('', $new_invoice_bk->invoice_terms);
		$this->assertEquals('', $new_invoice_bk->po_number);
		$this->assertEquals('18.35', (string) $new_invoice_bk->balance_due);
		$this->assertEquals('18.35', (string) $new_invoice_bk->total);
		$this->assertEquals('0', (string) $new_invoice_bk->discount);
		$this->assertEquals('0', (string) $new_invoice_bk->discount_amount_post_tax);

		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(2, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(15.55, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(2.80, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(18.35, (float) $invoice_items[0]->line_total);
		$this->assertEquals(15.55, (float) $invoice_items[0]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(0, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(0, (int) $invoices_custom_field_values->count());
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertNotEmpty($invoice->xml_file);

	}

	public function test_if_invoice_updates_with_changes_4_icutt(){

		$inserted = $this->insertInvoice();

		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		
		$company_id = $inserted['company_id'];
		//$client = $inserted['client'];
		$c = $inserted['c'];
		
		$data['data']['invoice_details']['po_number'] = '';
		$data['data']['invoice_details']['global_discount_type'] = '';
		$data['data']['invoice_details']['global_discount'] = '';
		$data['data']['invoice_terms'] = '';
		InvoicesCustomField::truncate();
		$data['custom_fields'] = [];
		$data['settings'] = [
			'payment_method'	=>	PAYMENT_STRIPE,
			'send_invoice_in_email'	=>	false
		];

		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);

		$new_invoice_bk = Invoice::find(1);
		$old_invoice = Arr::except($invoice->getAttributes(), ['updated_at', 'invoice_terms', 'balance_due', 'total', 'discount', 'discount_amount_post_tax', 'po_number', 'payment_method']);
		$new_invoice = Arr::except($new_invoice_bk->getAttributes(), ['updated_at', 'invoice_terms', 'balance_due', 'total', 'discount', 'discount_amount_post_tax', 'po_number', 'payment_method']);
		$this->assertEquals($old_invoice, $new_invoice);

		$this->assertEquals('', $new_invoice_bk->invoice_terms);
		$this->assertEquals(PAYMENT_STRIPE, (int) $new_invoice_bk->payment_method);
		$this->assertEquals('', $new_invoice_bk->po_number);
		$this->assertEquals('18.35', (string) $new_invoice_bk->balance_due);
		$this->assertEquals('18.35', (string) $new_invoice_bk->total);
		$this->assertEquals('0', (string) $new_invoice_bk->discount);
		$this->assertEquals('0', (string) $new_invoice_bk->discount_amount_post_tax);

		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(2, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(15.55, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(2.80, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(18.35, (float) $invoice_items[0]->line_total);
		$this->assertEquals(15.55, (float) $invoice_items[0]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(0, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(0, (int) $invoices_custom_field_values->count());
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertNotEmpty($invoice->xml_file);

	}


	public function test_if_invoice_updates_with_changes_5_icutt(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		
		$company_id = $inserted['company_id'];
		//$client = $inserted['client'];
		$c = $inserted['c'];

		$currency = Currency::where('id', '=', 5)->first();
		$industry = Industry::inRandomOrder()->first();
		$country = Country::inRandomOrder()->first();
		$this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $c['headers']);
		
		$new_client = Client::orderBy('id', 'desc')->first();
		
		$data['data']['invoice_details']['po_number'] = 'p ed';
		$data['data']['invoice_details']['global_discount_type'] = 'amount';
		$data['data']['invoice_details']['global_discount'] = '15';
		$data['data']['invoice_details']['invoice_date']['value'] = '2025-06-23T14:32:47.853Z';
		$data['data']['invoice_details']['invoice_number']['value'] = 'ABC123';
		$data['data']['invoice_details']['due_date']['value'] = '2026-05-23T14:32:47.853Z';
		$data['data']['invoice_details']['client']['client_id'] = $new_client->id;
		$data['data']['invoice_terms'] = 'terms edited';

		$data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b31",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 165.50,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			],
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b32",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-1234",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 10,
				"quantity" 			=> 3,
				"tax" 				=> 30,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "10",
				"discount" 			=> 5,
				"custom_tax_ctax_2" 	=> "15",
				
			]
		];
		
		$data['settings'] = [
			'payment_method'		=>	PAYMENT_STRIPE,
			'send_invoice_in_email'	=>	false
		];

		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);

		$new_invoice = Invoice::find(1);
		

		$this->assertEquals(2, (int) $new_invoice->client_id);
		$this->assertEquals($new_client->first_name, $new_invoice->first_name);
		$this->assertEquals($new_client->last_name, $new_invoice->last_name);
		$this->assertEquals($new_client->last_name, $new_invoice->last_name);
		$this->assertEquals($new_client->first_name.' '.$new_client->last_name, $new_invoice->full_name);
		$this->assertEquals($new_client->client_company_name, $new_invoice->client_company);
		$this->assertEquals(5, (int) $new_invoice->currency_id);
		$this->assertEquals($company_id, (int) $new_invoice->company_id);
		$this->assertEquals('ABC123', $new_invoice->invoice_number);
		$this->assertEquals('2025-06-23 14:32:47', (string) $new_invoice->invoice_date);
		$this->assertEquals('2026-05-23 14:32:47', (string) $new_invoice->due_date);
		$this->assertEquals('p ed', $new_invoice->po_number);
		$this->assertEquals('15', (string) $new_invoice->discount);
		$this->assertEquals('2', (string) $new_invoice->discount_type);
		$this->assertEquals('15', (string) $new_invoice->discount_amount_post_tax);
		$this->assertEquals('1.5', (string) $new_invoice->discount_amount_pre_tax);
		$this->assertEquals('195.5', (string) $new_invoice->subtotal);
		$this->assertEquals('45.47', (string) $new_invoice->tax_amount);
		$this->assertEquals('224.47', (string) $new_invoice->balance_due);
		$this->assertEquals('224.47', (string) $new_invoice->total);
		$this->assertEquals('0', (string) $new_invoice->refunded_amount);
		$this->assertEquals(InvoiceStatus::PENDING->value, (string) $new_invoice->status);
		$this->assertNotEmpty($new_invoice->pdf_file);
		$this->assertEmpty($new_invoice->xml_file);
		$this->assertEquals('terms edited', (string) $new_invoice->invoice_terms);
		$this->assertEquals(PAYMENT_STRIPE, (string) $new_invoice->payment_method);
		$this->assertEquals('0', (string) $new_invoice->pattern_matched);
		$this->assertEquals('0', (string) $new_invoice->scan_chars);
		$this->assertEquals('330', (string) $new_invoice->timezone_offset_minutes);
		$this->assertEquals('[{"id":1,"tax":false,"text":"Item","type":"normal","value":"item","mapped":["product_id"]},{"id":2,"tax":false,"text":"Description","type":"normal","value":"description","mapped":["description"]},{"id":3,"tax":false,"text":"Unit cost","type":"normal","value":"unit_cost","mapped":["unit_price"]},{"id":4,"tax":false,"text":"Quantity","type":"normal","value":"quantity","mapped":["quantity"]},{"id":6,"tax":true,"text":"Tax","type":"normal","value":"tax","mapped":["tax"],"tax_rate":0},{"id":13,"tax":false,"text":"c field","type":"custom","value":"c field","mapped":"","tax_rate":0,"id_column":1},{"id":7,"tax":false,"text":"Line total","type":"normal","value":"line_total","mapped":["line_total"]},{"id":12,"tax":true,"text":"ctax 1","type":"custom","value":"ctax 1","mapped":"","tax_rate":5,"id_column":2},{"id":13,"tax":true,"text":"ctax 2","type":"custom","value":"ctax 2","mapped":"","tax_rate":2.5,"id_column":3}]', (string) $new_invoice->settings_snapshot);
		$this->assertEquals('0', (string) $new_invoice->reminders_sent);
		$this->assertEquals($new_invoice->created_at, $new_invoice->last_reminder_sent_at);
		$this->assertNotNull($new_invoice->last_reminder_sent_at);
		
		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(2, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(165.5, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(29.79, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(195.29, (float) $invoice_items[0]->line_total);
		$this->assertEquals(165.5, (float) $invoice_items[0]->line_subtotal);

		$this->assertEquals(3, $invoice_items[1]->id);
		$this->assertEquals('bla-1234', $invoice_items[1]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[1]->invoice_id);
		$this->assertEquals(1, $invoice_items[1]->product_id);
		$this->assertEquals(10, (float) $invoice_items[1]->unit_price);
		$this->assertEquals(3, (int) $invoice_items[1]->quantity);
		$this->assertEquals(30, (int) $invoice_items[1]->tax);
		$this->assertEquals(15.68, (float) $invoice_items[1]->tax_amount);
		$this->assertEquals(44.18, (float) $invoice_items[1]->line_total);
		$this->assertEquals(28.5, (float) $invoice_items[1]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(10, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(10, (int) $invoices_custom_field_values->count());
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertEmpty($invoice->xml_file);

	}


	public function test_if_invoice_updates_with_changes_6_icutt(){

		$inserted = $this->insertInvoice();
		
		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		
		$company_id = $inserted['company_id'];
		//$client = $inserted['client'];
		$c = $inserted['c'];

		$currency = Currency::where('id', '=', 5)->first();
		$industry = Industry::inRandomOrder()->first();
		$country = Country::inRandomOrder()->first();
		$this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $c['headers']);
		
		$new_client = Client::orderBy('id', 'desc')->first();

		$new_data = $data;
		
		$new_data['data']['invoice_details']['po_number'] = 'p ed';
		$new_data['data']['invoice_details']['global_discount_type'] = 'amount';
		$new_data['data']['invoice_details']['global_discount'] = '15';
		$new_data['data']['invoice_details']['invoice_date']['value'] = '2025-06-23T14:32:47.853Z';
		$new_data['data']['invoice_details']['invoice_number']['value'] = 'ABC123';
		$new_data['data']['invoice_details']['due_date']['value'] = '2026-05-23T14:32:47.853Z';
		$new_data['data']['invoice_details']['client']['client_id'] = $new_client->id;
		$new_data['data']['invoice_terms'] = 'terms edited';

		$new_data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b36",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 165.50,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			],
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b32",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-1234",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 10,
				"quantity" 			=> 3,
				"tax" 				=> 30,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "10",
				"discount" 			=> 5,
				"custom_tax_ctax_2" 	=> "15",
				
			],
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b32",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-12345",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 85.77,
				"quantity" 			=> 2,
				"tax" 				=> 25.65,
				"normal_c_field" 	=> "556",
				"custom_tax_ctax_1" 	=> "3.50",
				"discount" 			=> 6.66,
				"custom_tax_ctax_2" 	=> "2.50",
				
			]
		];
		
		$new_data['settings'] = [
			'payment_method'		=>	PAYMENT_STRIPE,
			'send_invoice_in_email'	=>	false
		];

		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $new_data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);

		$new_invoice = Invoice::find(1);
		

		$this->assertEquals(2, (int) $new_invoice->client_id);
		$this->assertEquals($new_client->first_name, $new_invoice->first_name);
		$this->assertEquals($new_client->last_name, $new_invoice->last_name);
		$this->assertEquals($new_client->last_name, $new_invoice->last_name);
		$this->assertEquals($new_client->first_name.' '.$new_client->last_name, $new_invoice->full_name);
		$this->assertEquals($new_client->client_company_name, $new_invoice->client_company);
		$this->assertEquals(5, (int) $new_invoice->currency_id);
		$this->assertEquals($company_id, (int) $new_invoice->company_id);
		$this->assertEquals('ABC123', $new_invoice->invoice_number);
		$this->assertEquals('2025-06-23 14:32:47', (string) $new_invoice->invoice_date);
		$this->assertEquals('2026-05-23 14:32:47', (string) $new_invoice->due_date);
		$this->assertEquals('p ed', $new_invoice->po_number);
		$this->assertEquals('15', (string) $new_invoice->discount);
		$this->assertEquals('2', (string) $new_invoice->discount_type);
		$this->assertEquals('15', (string) $new_invoice->discount_amount_post_tax);
		$this->assertEquals('12.92', (string) $new_invoice->discount_amount_pre_tax);
		$this->assertEquals('367.04', $new_invoice->subtotal);
		$this->assertEquals('96.14', (string) $new_invoice->tax_amount);
		$this->assertEquals('435.26', (string) $new_invoice->balance_due);
		$this->assertEquals('435.26', (string) $new_invoice->total);
		$this->assertEquals('0', (string) $new_invoice->refunded_amount);
		$this->assertEquals(InvoiceStatus::PENDING->value, (string) $new_invoice->status);
		$this->assertNotEmpty($new_invoice->pdf_file);
		$this->assertEmpty($new_invoice->xml_file);
		$this->assertEquals('terms edited', (string) $new_invoice->invoice_terms);
		$this->assertEquals(PAYMENT_STRIPE, (string) $new_invoice->payment_method);
		$this->assertEquals('0', (string) $new_invoice->pattern_matched);
		$this->assertEquals('0', (string) $new_invoice->scan_chars);
		$this->assertEquals('330', (string) $new_invoice->timezone_offset_minutes);
		$this->assertEquals('[{"id":1,"tax":false,"text":"Item","type":"normal","value":"item","mapped":["product_id"]},{"id":2,"tax":false,"text":"Description","type":"normal","value":"description","mapped":["description"]},{"id":3,"tax":false,"text":"Unit cost","type":"normal","value":"unit_cost","mapped":["unit_price"]},{"id":4,"tax":false,"text":"Quantity","type":"normal","value":"quantity","mapped":["quantity"]},{"id":6,"tax":true,"text":"Tax","type":"normal","value":"tax","mapped":["tax"],"tax_rate":0},{"id":13,"tax":false,"text":"c field","type":"custom","value":"c field","mapped":"","tax_rate":0,"id_column":1},{"id":7,"tax":false,"text":"Line total","type":"normal","value":"line_total","mapped":["line_total"]},{"id":12,"tax":true,"text":"ctax 1","type":"custom","value":"ctax 1","mapped":"","tax_rate":5,"id_column":2},{"id":13,"tax":true,"text":"ctax 2","type":"custom","value":"ctax 2","mapped":"","tax_rate":2.5,"id_column":3}]', (string) $new_invoice->settings_snapshot);
		$this->assertEquals('0', (string) $new_invoice->reminders_sent);
		$this->assertEquals($new_invoice->created_at, $new_invoice->last_reminder_sent_at);
		$this->assertNotNull($new_invoice->last_reminder_sent_at);
		
		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(2, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(165.5, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(29.79, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(195.29, (float) $invoice_items[0]->line_total);
		$this->assertEquals(165.5, (float) $invoice_items[0]->line_subtotal);

		$this->assertEquals(3, $invoice_items[1]->id);
		$this->assertEquals('bla-1234', $invoice_items[1]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[1]->invoice_id);
		$this->assertEquals(1, $invoice_items[1]->product_id);
		$this->assertEquals(10, (float) $invoice_items[1]->unit_price);
		$this->assertEquals(3, (int) $invoice_items[1]->quantity);
		$this->assertEquals(30, (int) $invoice_items[1]->tax);
		$this->assertEquals(15.68, (float) $invoice_items[1]->tax_amount);
		$this->assertEquals(44.18, (float) $invoice_items[1]->line_total);
		$this->assertEquals(28.5, (float) $invoice_items[1]->line_subtotal);

		$this->assertEquals(4, $invoice_items[2]->id);
		$this->assertEquals('bla-12345', $invoice_items[2]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[2]->invoice_id);
		$this->assertEquals(1, $invoice_items[2]->product_id);
		$this->assertEquals(85.77, (float) $invoice_items[2]->unit_price);
		$this->assertEquals(2, (int) $invoice_items[2]->quantity);
		$this->assertEquals(25, (int) $invoice_items[2]->tax);
		$this->assertEquals(50.68, (float) $invoice_items[2]->tax_amount);
		$this->assertEquals(210.79, (float) $invoice_items[2]->line_total);
		$this->assertEquals(160.12, (float) $invoice_items[2]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(10, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(10, (int) $invoices_custom_field_values->count());
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertEmpty($invoice->xml_file);

		//arrange product rows.
		$new_data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b44",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 165.50,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			],
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b46",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-12345",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 85.77,
				"quantity" 			=> 2,
				"tax" 				=> 25.65,
				"normal_c_field" 	=> "556",
				"custom_tax_ctax_1" 	=> "3.50",
				"discount" 			=> 6.66,
				"custom_tax_ctax_2" 	=> "2.50",
				
			],
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b45",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-1234",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 10,
				"quantity" 			=> 3,
				"tax" 				=> 30,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "10",
				"discount" 			=> 5,
				"custom_tax_ctax_2" 	=> "15",
				
			],
		];
		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $new_data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);
		
		
		$new_invoice = Invoice::find($invoice->id);
		
		$this->assertEquals(2, (int) $new_invoice->client_id);
		$this->assertEquals($new_client->first_name, $new_invoice->first_name);
		$this->assertEquals($new_client->last_name, $new_invoice->last_name);
		$this->assertEquals($new_client->last_name, $new_invoice->last_name);
		$this->assertEquals($new_client->first_name.' '.$new_client->last_name, $new_invoice->full_name);
		$this->assertEquals($new_client->client_company_name, $new_invoice->client_company);
		$this->assertEquals(5, (int) $new_invoice->currency_id);
		$this->assertEquals($company_id, (int) $new_invoice->company_id);
		$this->assertEquals('ABC123', $new_invoice->invoice_number);
		$this->assertEquals('2025-06-23 14:32:47', (string) $new_invoice->invoice_date);
		$this->assertEquals('2026-05-23 14:32:47', (string) $new_invoice->due_date);
		$this->assertEquals('p ed', $new_invoice->po_number);
		$this->assertEquals('15', (string) $new_invoice->discount);
		$this->assertEquals('2', (string) $new_invoice->discount_type);
		$this->assertEquals('15', (string) $new_invoice->discount_amount_post_tax);
		$this->assertEquals('12.92', (string) $new_invoice->discount_amount_pre_tax);
		$this->assertEquals('367.04', $new_invoice->subtotal);
		$this->assertEquals('96.14', (string) $new_invoice->tax_amount);
		$this->assertEquals('435.26', (string) $new_invoice->balance_due);
		$this->assertEquals('435.26', (string) $new_invoice->total);
		$this->assertEquals('0', (string) $new_invoice->refunded_amount);
		$this->assertEquals(InvoiceStatus::PENDING->value, (string) $new_invoice->status);
		$this->assertNotEmpty($new_invoice->pdf_file);
		$this->assertEmpty($new_invoice->xml_file);
		$this->assertEquals('terms edited', (string) $new_invoice->invoice_terms);
		$this->assertEquals(PAYMENT_STRIPE, (string) $new_invoice->payment_method);
		$this->assertEquals('0', (string) $new_invoice->pattern_matched);
		$this->assertEquals('0', (string) $new_invoice->scan_chars);
		$this->assertEquals('330', (string) $new_invoice->timezone_offset_minutes);
		$this->assertEquals('[{"id":1,"tax":false,"text":"Item","type":"normal","value":"item","mapped":["product_id"]},{"id":2,"tax":false,"text":"Description","type":"normal","value":"description","mapped":["description"]},{"id":3,"tax":false,"text":"Unit cost","type":"normal","value":"unit_cost","mapped":["unit_price"]},{"id":4,"tax":false,"text":"Quantity","type":"normal","value":"quantity","mapped":["quantity"]},{"id":6,"tax":true,"text":"Tax","type":"normal","value":"tax","mapped":["tax"],"tax_rate":0},{"id":13,"tax":false,"text":"c field","type":"custom","value":"c field","mapped":"","tax_rate":0,"id_column":1},{"id":7,"tax":false,"text":"Line total","type":"normal","value":"line_total","mapped":["line_total"]},{"id":12,"tax":true,"text":"ctax 1","type":"custom","value":"ctax 1","mapped":"","tax_rate":5,"id_column":2},{"id":13,"tax":true,"text":"ctax 2","type":"custom","value":"ctax 2","mapped":"","tax_rate":2.5,"id_column":3}]', (string) $new_invoice->settings_snapshot);
		$this->assertEquals('0', (string) $new_invoice->reminders_sent);
		$this->assertEquals($new_invoice->created_at, $new_invoice->last_reminder_sent_at);
		$this->assertNotNull($new_invoice->last_reminder_sent_at);
		
		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(5, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(165.5, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(29.79, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(195.29, (float) $invoice_items[0]->line_total);
		$this->assertEquals(165.5, (float) $invoice_items[0]->line_subtotal);

		$this->assertEquals(7, $invoice_items[2]->id);
		$this->assertEquals('bla-1234', $invoice_items[2]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[2]->invoice_id);
		$this->assertEquals(1, $invoice_items[2]->product_id);
		$this->assertEquals(10, (float) $invoice_items[2]->unit_price);
		$this->assertEquals(3, (int) $invoice_items[2]->quantity);
		$this->assertEquals(30, (int) $invoice_items[2]->tax);
		$this->assertEquals(15.68, (float) $invoice_items[2]->tax_amount);
		$this->assertEquals(44.18, (float) $invoice_items[2]->line_total);
		$this->assertEquals(28.5, (float) $invoice_items[2]->line_subtotal);

		$this->assertEquals(6, $invoice_items[1]->id);
		$this->assertEquals('bla-12345', $invoice_items[1]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[1]->invoice_id);
		$this->assertEquals(1, $invoice_items[1]->product_id);
		$this->assertEquals(85.77, (float) $invoice_items[1]->unit_price);
		$this->assertEquals(2, (int) $invoice_items[1]->quantity);
		$this->assertEquals(25, (int) $invoice_items[1]->tax);
		$this->assertEquals(50.68, (float) $invoice_items[1]->tax_amount);
		$this->assertEquals(210.79, (float) $invoice_items[1]->line_total);
		$this->assertEquals(160.12, (float) $invoice_items[1]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(10, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(10, (int) $invoices_custom_field_values->count());
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertEmpty($invoice->xml_file);


		//with deletion
		$new_data['data']['product_rows'] = [
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b44",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-123",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 165.50,
				"quantity" 			=> 1,
				"tax" 				=> 5,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "6",
				"discount" 			=> 0,
				"custom_tax_ctax_2" 	=> "7",
				
			],
			[
				"id"		 		=> "b2c4ec6a-0ed8-4238-9737-ab6a7d3f4b45",
				"row_index" 		=>  0,
				"row_uuid" 			=> "bla-1234",
				"line_subtotal" 	=> '  ',
				"tax_amount"	 	=> '  ',
				"line_total" 		=> "16.33",
				"product_id" 		=>  1,
				"item" 				=> "prod 3",
				"description" 		=> "prod 3 desc",
				"unit_price" 		=> 10,
				"quantity" 			=> 3,
				"tax" 				=> 30,
				"normal_c_field" 	=> "555",
				"custom_tax_ctax_1" 	=> "10",
				"discount" 			=> 5,
				"custom_tax_ctax_2" 	=> "15",
				
			],
		];
		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $new_data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);
		
		
		$new_invoice = Invoice::find($invoice->id);
		
		$this->assertEquals(2, (int) $new_invoice->client_id);
		$this->assertEquals($new_client->first_name, $new_invoice->first_name);
		$this->assertEquals($new_client->last_name, $new_invoice->last_name);
		$this->assertEquals($new_client->last_name, $new_invoice->last_name);
		$this->assertEquals($new_client->first_name.' '.$new_client->last_name, $new_invoice->full_name);
		$this->assertEquals($new_client->client_company_name, $new_invoice->client_company);
		$this->assertEquals(5, (int) $new_invoice->currency_id);
		$this->assertEquals($company_id, (int) $new_invoice->company_id);
		$this->assertEquals('ABC123', $new_invoice->invoice_number);
		$this->assertEquals('2025-06-23 14:32:47', (string) $new_invoice->invoice_date);
		$this->assertEquals('2026-05-23 14:32:47', (string) $new_invoice->due_date);
		$this->assertEquals('p ed', $new_invoice->po_number);
		$this->assertEquals('15', (string) $new_invoice->discount);
		$this->assertEquals('2', (string) $new_invoice->discount_type);
		$this->assertEquals('15', (string) $new_invoice->discount_amount_post_tax);
		$this->assertEquals('1.5', (string) $new_invoice->discount_amount_pre_tax);
		$this->assertEquals('195.5', $new_invoice->subtotal);
		$this->assertEquals('45.47', (string) $new_invoice->tax_amount);
		$this->assertEquals('224.47', (string) $new_invoice->balance_due);
		$this->assertEquals('224.47', (string) $new_invoice->total);
		$this->assertEquals('0', (string) $new_invoice->refunded_amount);
		$this->assertEquals(InvoiceStatus::PENDING->value, (string) $new_invoice->status);
		$this->assertNotEmpty($new_invoice->pdf_file);
		$this->assertEmpty($new_invoice->xml_file);
		$this->assertEquals('terms edited', (string) $new_invoice->invoice_terms);
		$this->assertEquals(PAYMENT_STRIPE, (string) $new_invoice->payment_method);
		$this->assertEquals('0', (string) $new_invoice->pattern_matched);
		$this->assertEquals('0', (string) $new_invoice->scan_chars);
		$this->assertEquals('330', (string) $new_invoice->timezone_offset_minutes);
		$this->assertEquals('[{"id":1,"tax":false,"text":"Item","type":"normal","value":"item","mapped":["product_id"]},{"id":2,"tax":false,"text":"Description","type":"normal","value":"description","mapped":["description"]},{"id":3,"tax":false,"text":"Unit cost","type":"normal","value":"unit_cost","mapped":["unit_price"]},{"id":4,"tax":false,"text":"Quantity","type":"normal","value":"quantity","mapped":["quantity"]},{"id":6,"tax":true,"text":"Tax","type":"normal","value":"tax","mapped":["tax"],"tax_rate":0},{"id":13,"tax":false,"text":"c field","type":"custom","value":"c field","mapped":"","tax_rate":0,"id_column":1},{"id":7,"tax":false,"text":"Line total","type":"normal","value":"line_total","mapped":["line_total"]},{"id":12,"tax":true,"text":"ctax 1","type":"custom","value":"ctax 1","mapped":"","tax_rate":5,"id_column":2},{"id":13,"tax":true,"text":"ctax 2","type":"custom","value":"ctax 2","mapped":"","tax_rate":2.5,"id_column":3}]', (string) $new_invoice->settings_snapshot);
		$this->assertEquals('0', (string) $new_invoice->reminders_sent);
		$this->assertEquals($new_invoice->created_at, $new_invoice->last_reminder_sent_at);
		$this->assertNotNull($new_invoice->last_reminder_sent_at);
		
		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(8, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(165.5, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(29.79, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(195.29, (float) $invoice_items[0]->line_total);
		$this->assertEquals(165.5, (float) $invoice_items[0]->line_subtotal);

		$this->assertArrayNotHasKey(2, $invoice_items);

		$this->assertEquals(9, $invoice_items[1]->id);
		$this->assertEquals('bla-1234', $invoice_items[1]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[1]->invoice_id);
		$this->assertEquals(1, $invoice_items[1]->product_id);
		$this->assertEquals(10, (float) $invoice_items[1]->unit_price);
		$this->assertEquals(3, (int) $invoice_items[1]->quantity);
		$this->assertEquals(30, (int) $invoice_items[1]->tax);
		$this->assertEquals(15.68, (float) $invoice_items[1]->tax_amount);
		$this->assertEquals(44.18, (float) $invoice_items[1]->line_total);
		$this->assertEquals(28.5, (float) $invoice_items[1]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(10, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(10, (int) $invoices_custom_field_values->count());
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertEmpty($invoice->xml_file);

	}

	public function test_if_invoice_updates_with_changes_7_icutt(){

		$inserted = $this->insertInvoice();

		$invoice = $inserted['invoice'];
		$data = $inserted['data'];
		
		
		$company_id = $inserted['company_id'];
		//$client = $inserted['client'];
		$c = $inserted['c'];
		
		$data['data']['invoice_details']['po_number'] = '';
		$data['data']['invoice_details']['global_discount_type'] = '';
		$data['data']['invoice_details']['global_discount'] = '';
		$data['data']['invoice_terms'] = '';
		
		$this->addAllCustomFields($company_id, $c['headers'], -1,'invoice');
		$temp = $this->setCustomFields(false, InvoicesCustomField::class);
		
		$custom_fields_post = $temp['fields'];
		$custom_fields_post[0]['value'] = $custom_fields_post[0]['value'].' ed';
		$custom_fields_post[1]['value'] = $custom_fields_post[0]['value'].' ed';
		$custom_fields_post[2]['value'] = 'edited@some.com';
		$custom_fields_post[3]['value'] = 'two';
		$custom_fields_post[4]['value'] = '111';
		$custom_fields_post[5]['value'] = '2017-01-20T00:00:00.000Z';
		$custom_fields_post[6]['value'] = ["hours" => 5,
										"minutes" => 25,
										"seconds" => 10];
		$custom_fields_post[7]['value'] = '2018-01-18T11:08:15Z';
		$custom_fields_post[8]['value'] = '445566';
		$custom_fields_post[9]['value'][0] = 'one edited';

		$data['custom_fields'] = $custom_fields_post;
		$data['settings'] = [
			'payment_method'	=>	PAYMENT_STRIPE,
			'send_invoice_in_email'	=>	false
		];

		
		$response = $this->patch('/api/manage-invoices/'.$invoice->id, $data, $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals('invoice_updated', $json['validity']);

		$new_invoice_bk = Invoice::find(1);
		$old_invoice = Arr::except($invoice->getAttributes(), ['updated_at', 'invoice_terms', 'balance_due', 'total', 'discount', 'discount_amount_post_tax', 'po_number', 'payment_method']);
		$new_invoice = Arr::except($new_invoice_bk->getAttributes(), ['updated_at', 'invoice_terms', 'balance_due', 'total', 'discount', 'discount_amount_post_tax', 'po_number', 'payment_method']);
		$this->assertEquals($old_invoice, $new_invoice);

		$this->assertEquals('', $new_invoice_bk->invoice_terms);
		$this->assertEquals(PAYMENT_STRIPE, (int) $new_invoice_bk->payment_method);
		$this->assertEquals('', $new_invoice_bk->po_number);
		$this->assertEquals('18.35', (string) $new_invoice_bk->balance_due);
		$this->assertEquals('18.35', (string) $new_invoice_bk->total);
		$this->assertEquals('0', (string) $new_invoice_bk->discount);
		$this->assertEquals('0', (string) $new_invoice_bk->discount_amount_post_tax);

		$invoice_items = InvoiceItem::all();
		
		$this->assertEquals(2, $invoice_items[0]->id);
		$this->assertEquals('bla-123', $invoice_items[0]->row_uuid);
		$this->assertEquals($invoice->id, $invoice_items[0]->invoice_id);
		$this->assertEquals(1, $invoice_items[0]->product_id);
		$this->assertEquals(15.55, (float) $invoice_items[0]->unit_price);
		$this->assertEquals(1, (int) $invoice_items[0]->quantity);
		$this->assertEquals(5, (int) $invoice_items[0]->tax);
		$this->assertEquals(2.80, (float) $invoice_items[0]->tax_amount);
		$this->assertEquals(18.35, (float) $invoice_items[0]->line_total);
		$this->assertEquals(15.55, (float) $invoice_items[0]->line_subtotal);

		$apcv = AdditionalProductColumnsFieldValue::all();
		$this->assertEquals('555', (string) $apcv[0]->value);
		$this->assertEquals('6', (string) $apcv[1]->value);
		$this->assertEquals('7', (string) $apcv[2]->value);

		$invoices_custom_fields = InvoicesCustomField::all();
		$this->assertEquals(10, (int) $invoices_custom_fields->count());

		$invoices_custom_field_values = InvoiceCustomFieldValue::all();
		$this->assertEquals(10, (int) $invoices_custom_field_values->count());
		
		$s = SettingsSection::where('type', '=', ISC_INVOICE_NUMBER_RESET_TYPE)->first();
		$s_json = json_decode($s->settings_json, true);
		$this->assertEquals(0, (int) $s_json['reset']);

		$custom_field_values = InvoiceCustomFieldValue::all()->toArray();
		
		$this->assertEquals('some text ed', $custom_field_values[0]['field_value']);
		$this->assertEquals('some text ed ed', $custom_field_values[1]['field_value']);
		$this->assertEquals('edited@some.com', $custom_field_values[2]['field_value']);
		$this->assertEquals('two', $custom_field_values[3]['field_value']);
		$this->assertEquals('111', $custom_field_values[4]['field_value']);
		$this->assertEquals('2017-01-20T00:00:00.000Z', $custom_field_values[5]['field_value']);
		$this->assertEquals('05:25 AM', $custom_field_values[6]['field_value']);
		$this->assertEquals('2018-01-18T11:08:15Z', $custom_field_values[7]['field_value']);
		$this->assertEquals('445566', $custom_field_values[8]['field_value']);
		$this->assertEquals('["one edited"]', $custom_field_values[9]['field_value']);
		
		//for snapshot
		$snapshot = app(Snapshot::class)
						->setCompanyId($company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
		
		$invoice_snapshot_obj = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		$this->assertEquals(json_encode($snapshot), json_encode($invoice_snapshot_obj->snapshot));

		$invoice = Invoice::find($invoice->id);
		$this->assertNotEmpty($invoice->pdf_file);
		$this->assertNotEmpty($invoice->xml_file);

	}



}
