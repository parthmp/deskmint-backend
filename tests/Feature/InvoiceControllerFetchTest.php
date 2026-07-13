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

	// public function test_if_it_fetches_initial_data_3_icft(){

	// 	$device = 'device 123';
	// 	$c = $this->set_access($device);
	// 	$company_id = $this->set_default_company();

	// 	$client = $this->insertClient($company_id, $c['headers']);

	// 	$response = $this->get('/api/manage-invoices/fetch-initial-data?company_id='.$company_id.'&timezone_offset_minutes=330', $c['headers']);
	// 	//$response->assertStatus(200);
	// 	$json = $response->json();
	// 	dd($json);

	// }

}
