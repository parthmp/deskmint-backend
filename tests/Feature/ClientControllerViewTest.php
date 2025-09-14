<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class ClientControllerViewTest extends TestCase{
	
    use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	private function addNewClient(){
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();
		
		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $c['headers']);
		return [
			'headers'		=>	$c['headers'],
			'company_id'	=>	$company_id
		];

	}

	public function test_if_it_does_fetch_the_client_data_ccvt():void{

		$temp = $this->addNewClient();

		$id = Client::first()->pluck('id')->toArray();
		$id = $id[0];

		$params = [
			'company_id'	=>	$temp['company_id'],
		];

		$response = $this->withHeaders($temp['headers'])->get('/api/manage-clients/'.$id.'?'. http_build_query($params));

		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertArrayHasKey('client_info', $json);
		$this->assertArrayHasKey('billing_country', $json['client_info']);
		$this->assertArrayHasKey('shipping_country', $json['client_info']);
		$this->assertArrayHasKey('contact_info', $json);
		$this->assertArrayHasKey('custom_fields', $json);

	}

}
