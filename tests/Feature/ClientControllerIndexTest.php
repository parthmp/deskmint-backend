<?php

namespace Tests\Feature;

use App\Models\ClientsCustomField;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomFieldType;
use App\Models\Industry;
use App\Models\SettingsIndexColumn;
use App\Models\UserIndexColumn;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
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

	public function test_if_arrange_columns_saves_correctly_for_user_with_no_custom_fields():void{

		UserIndexColumn::truncate();

		$company_id = $this->set_default_company();
		$c = $this->set_access('device 123');

		$columns = Schema::getColumnListing('clients');
		$columns = array_diff($columns, ['deleted_at', 'updated_at']);
		$columns_ar = [];

		$counter = 1;
		foreach($columns as $column){
			
			$columns_ar[] = [
				'id'				=>	$counter,
				'label'				=>	$column,
				'text'				=>	$column,
				'type'				=>	'normal',
				'is_date'			=>	false,
				'searchable'		=>	false,
				'show'				=>	false,
			];
			$counter++;

		}
		
		$columns_ar[1]['show'] = true;
		$columns_ar[1]['searchable'] = true;
		$columns_ar[2]['searchable'] = true;
		
		$response = $this->post('/api/manage-clients/save-arranged-columns', [
			'columns' 		=> $columns_ar,
			'company_id'	=> $company_id
		], $c['headers']);
		
		$response->assertStatus(200);
		$response = $response->json();
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		/* now test for true values */
		$user_settings = UserIndexColumn::where([['user_id', '=', $c['user']->id], ['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();
		$columns_json = json_decode($user_settings->columns_json, true);
		
		$this->assertTrue((bool)$columns_json[1]['show']);
		$this->assertTrue((bool)$columns_json[1]['searchable']);
		$this->assertTrue((bool)$columns_json[2]['searchable']);
		$this->assertFalse((bool)$columns_json[3]['searchable']);


	}


	public function test_if_client_index_page_arrange_columns_fetches_correctly_with_admin_settings_set():void{

		$company_id = $this->set_default_company();
		$c = $this->set_access('device 123');

		$params = [
			'company_id'	=>	$company_id,
		];

		/* set global settings */
		SettingsIndexColumn::truncate();

		$columns = Schema::getColumnListing('clients');
		$columns = array_diff($columns, ['deleted_at', 'updated_at']);
		$columns_ar = [];

		$counter = 1;
		foreach($columns as $column){
			
			$columns_ar[] = [
				'id'				=>	$counter,
				'label'				=>	$column,
				'text'				=>	$column,
				'type'				=>	'normal',
				'is_date'			=>	false,
				'searchable'		=>	false,
				'show'				=>	false,
			];
			$counter++;

		}
		
		$columns_ar[1]['show'] = true;
		$columns_ar[1]['searchable'] = true;
		$columns_ar[2]['searchable'] = true;

		$setting = new SettingsIndexColumn();
		$setting->company_id = $company_id;
		$setting->feature_name = 'clients';
		$setting->columns_json = json_encode($columns_ar);
		$setting->created_at = now();
		$setting->updated_at = now();
		$setting->save();
		

		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients/fetch-arranged-columns?'. http_build_query($params));
		$response->assertStatus(200);
		
		/* now test for true values */
		$global_setting = SettingsIndexColumn::where([['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();
		$columns_json = json_decode($global_setting->columns_json, true);
		
		$this->assertTrue((bool)$columns_json[1]['show']);
		$this->assertTrue((bool)$columns_json[1]['searchable']);
		$this->assertTrue((bool)$columns_json[2]['searchable']);
		$this->assertFalse((bool)$columns_json[3]['searchable']);
		
		
	}

	public function test_if_client_index_page_arrange_columns_fetches_correctly_with_no_admin_settings_set_and_custom_fields():void{

		$company_id = $this->set_default_company();
		$c = $this->set_access('device 123');

		$params = [
			'company_id'	=>	$company_id,
		];

		$this->addAllCustomFields($company_id, $c['headers'], 1);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients/fetch-arranged-columns?'. http_build_query($params));

		$response->assertStatus(200);
		$json = $response->json();
		
		$last = $json[count($json)-1];
		$this->assertEquals('custom', $last['type']);
		
	}

	public function test_if_arrange_columns_saves_correctly_for_user_with_some_custom_fields():void{

		UserIndexColumn::truncate();

		$company_id = $this->set_default_company();
		$c = $this->set_access('device 123');

		$columns = Schema::getColumnListing('clients');
		$columns = array_diff($columns, ['deleted_at', 'updated_at']);
		$columns_ar = [];

		$counter = 1;
		foreach($columns as $column){
			
			$columns_ar[] = [
				'id'				=>	$counter,
				'label'				=>	$column,
				'text'				=>	$column,
				'type'				=>	'normal',
				'is_date'			=>	false,
				'searchable'		=>	false,
				'show'				=>	false,
			];
			$counter++;

		}
		
		$columns_ar[1]['show'] = true;
		$columns_ar[1]['searchable'] = true;
		$columns_ar[2]['searchable'] = true;

		/* now for custom field(s) */
		$this->addAllCustomFields($company_id, $c['headers'], 1);
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->first();
		array_push($columns_ar, [
			'id'						=>	($counter+=1),
			'label'						=>	$custom_fields_db->label,
			'text'						=>	$custom_fields_db->label,
			'type'						=>	'custom',
			'is_date'					=>	false,
			'searchable'				=>	true,
			'show'						=>	false,
			'clients_custom_fields_id'	=> $custom_fields_db->id
		]);

		
		$response = $this->post('/api/manage-clients/save-arranged-columns', [
			'columns' 		=> $columns_ar,
			'company_id'	=> $company_id
		], $c['headers']);
		
		$response->assertStatus(200);
		$response = $response->json();
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		/* now test for true values */
		$user_settings = UserIndexColumn::where([['user_id', '=', $c['user']->id], ['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();
		$columns_json = json_decode($user_settings->columns_json, true);
		
		$this->assertTrue((bool)$columns_json[1]['show']);
		$this->assertTrue((bool)$columns_json[1]['searchable']);
		$this->assertTrue((bool)$columns_json[2]['searchable']);
		$this->assertFalse((bool)$columns_json[3]['searchable']);

		$last = $columns_json[count($columns_json)-1];
		$this->assertEquals('custom', $last['type']);
		$this->assertTrue((bool)$last['searchable']);


	}

	public function test_if_index_loads_for_clients_with_no_settings_added():void{
		
		$c = $this->addNewClients(20);
		
		$params = [
			'company_id'		=>	$c['company_id'],
			'default_per_page'	=>	10
		];
		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients?'. http_build_query($params));
		
		$response->assertStatus(200);
		$json = $response->json();

		$this->assertEquals('first_name', $json['table_data']['columns'][0]['label']);
		$this->assertEquals('last_name', $json['table_data']['columns'][1]['label']);
		$this->assertEquals('email', $json['table_data']['columns'][2]['label']);
		$this->assertEquals('created_at', $json['table_data']['columns'][3]['label']);
		$this->assertEquals('actions', $json['table_data']['columns'][4]['label']);

		$this->assertEquals(10, (int)count($json['table_data']['rows']));

	}

}
