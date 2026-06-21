<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Traits\SettingsDefault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceControllerValidationTest extends TestCase
{
    use SettingsDefault, RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	public function insertClient(int $company_id, mixed $headers) : Client {
		DB::table('clients')->truncate();
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		$country = Country::inRandomOrder()->first();
		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $headers);
		$response->assertStatus(200);
		return Client::first();
	}

	public function test_invoice_posting_without_custom_fields_invalid_tab_data_1_icvt(){ //default product fields.

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoices', [
			'data.invoice_details.client.client_id'		=>	555,
		], $c['headers']);

		$json = $response->json();
		
		$this->assertEquals('invalid_request_company', $json['validity']);

	}

	public function test_invoice_posting_without_custom_fields_invalid_tab_data_2_icvt(){ //default product fields.

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoices', [
			'data.invoice_details.client.client_id'		=>	555,
			'company_id'								=>	$company_id,
		], $c['headers']);

		$json = $response->json();
		
		$this->assertEquals('invalid_request_t0', $json['validity']);

	}

	public function test_invoice_posting_without_custom_fields_invalid_tab_data_3_icvt(){ //default product fields.

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers']);
		
		$response = $this->post('/api/manage-invoices', [
			'company_id'								=>	$company_id,
			'data.invoice_details.client.client_id'		=>	$client->id,
			'data.invoice_details.invoice_date.value'	=>	'',
			'data.invoice_details.invoice_number.value'	=>	'   ',
			'data.invoice_details.due_date.value'		=>	'',
		], $c['headers']);

		$json = $response->json();
		
		$this->assertEquals('invalid_request_t0', $json['validity']);


	}

}
