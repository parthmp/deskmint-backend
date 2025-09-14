<?php

namespace Tests\Feature;

use App\Models\ClientsCustomField;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomFieldType;
use App\Models\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class ClientControllerIndexTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	private function addNewClients($num = 1){
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();
		
		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		for($z = 0 ; $z < $num ; $z++){
			$this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $c['headers']);
		}
		
		return [
			'headers'		=>	$c['headers'],
			'company_id'	=>	$company_id
		];

	}

	public function test_if_client_index_page_arrange_columns_loads_default():void{

		$company_id = $this->set_default_company();
		$c = $this->set_access('device 123');

		$params = [
			'company_id'	=>	$company_id,
		];

		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients/fetch-arranged-columns?'. http_build_query($params));

		$response->assertStatus(200);
		$json = $response->json();
		$this->assertNotEmpty($json);
		
	}

	public function test_if_client_index_page_arrange_columns_loads_default_with_custom_columns():void{

		$company_id = $this->set_default_company();
		$c = $this->set_access('device 123');

		$params = [
			'company_id'	=>	$company_id,
		];

		$this->addAllCustomFields($company_id, $c['headers']);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients/fetch-arranged-columns?'. http_build_query($params));

		$response->assertStatus(200);
		$json = $response->json();
		$this->assertNotEmpty($json);

		$field_types = CustomFieldType::where('input_type', '<>', 'multiselect')->count();
		
		$custom_fields_count = 0;
		foreach($json as $field){
			if($field['type'] === 'custom'){
				$custom_fields_count++;
			}
		}

		$this->assertEquals($field_types, $custom_fields_count);
		
	}

	public function test_if_client_index_page_arrange_columns_loads_default_with_some_custom_columns_removed():void{

		$company_id = $this->set_default_company();
		$c = $this->set_access('device 123');

		$params = [
			'company_id'	=>	$company_id,
		];

		$this->addAllCustomFields($company_id, $c['headers']);

		$custom_field_ids = ClientsCustomField::whereHas('customFieldType', function($q){
			$q->where('input_type','<>', 'multiselect');
		})->limit(3)->pluck('id')->toArray();
		$this->deleteClientsCustomFields($custom_field_ids, $c['headers'], $company_id);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients/fetch-arranged-columns?'. http_build_query($params));

		$response->assertStatus(200);
		$json = $response->json();
		$this->assertNotEmpty($json);

		$field_types = CustomFieldType::where('input_type', '<>', 'multiselect')->count();
		$field_types -= 3;
		$custom_fields_count = 0;
		foreach($json as $field){
			if($field['type'] === 'custom'){
				$custom_fields_count++;
			}
		}

		$this->assertEquals($field_types, $custom_fields_count);
		
	}

}
