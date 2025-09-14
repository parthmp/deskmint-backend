<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class ClientControllerDeleteTest extends TestCase{
	
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
	
	public function test_if_it_fails_to_delete_the_client_without_ids_provided():void{

		$temp = $this->addNewClient();

		$response = $this->delete('/api/manage-clients', ['ids' => [], 'company_id' => $temp['company_id']], $temp['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}


	public function test_if_it_fails_to_delete_the_client_without_ids_provided2():void{

		$temp = $this->addNewClient();

		$response = $this->delete('/api/manage-clients', ['company_id' => $temp['company_id']], $temp['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}

	public function test_if_it_fails_to_delete_the_client_with_numeric_ids_provided2():void{

		$temp = $this->addNewClient();

		$response = $this->delete('/api/manage-clients', ['ids' => ['one', 'two'], 'company_id' => $temp['company_id']], $temp['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('non_numeric', $response['validity']);

	}

	public function test_if_it_deletes_the_client_with_valid_ids_provided_1():void{

		$temp = $this->addNewClient();

		$ids = Client::pluck('id')->toArray();
		
		$response = $this->delete('/api/manage-clients', ['ids' => $ids, 'company_id' => $temp['company_id']], $temp['headers']);

		$response->assertStatus(200);
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$ids = Client::pluck('id')->toArray();
		$this->assertEmpty($ids);

	}

}
