<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\Product;
use App\Traits\SettingsDefault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceCalculationTest extends TestCase {

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
		$this->assertEquals($default_product_rows_settings, (string) $invoice->settings_snapshot);

	}

}
