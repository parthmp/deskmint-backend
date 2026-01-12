<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\ClientsCustomField;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomFieldType;
use App\Models\Industry;
use App\Models\SettingsIndexColumn;
use App\Models\UserIndexColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class ClientControllerIndexTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	private function addNewClients($num = 1, $custom = false){
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();
		
		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();

		

		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		if($custom){
			$this->addAllCustomFields($company_id, $c['headers']);
			$temp = $this->setCustomFields();
		}
		for($z = 0 ; $z < $num ; $z++){
			if($custom){
				$this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id, $temp['fields']), $c['headers']);
			}else{
				$this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $c['headers']);
			}
			
		}
		
		return [
			'headers'		=>	$c['headers'],
			'company_id'	=>	$company_id
		];

	}

	private function getColumnsAr():array{
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

		return $columns_ar;
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

		$columns_ar = $this->getColumnsAr();
		
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

		$columns_ar = $this->getColumnsAr();

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
		
		$temp = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->with('customFieldType')->first()->toArray();
		
		if($temp['custom_field_type']['input_type'] === config('global.field_types')[9]){
			
			$this->assertEquals('normal', $last['type']);
		}else{
			$this->assertEquals('custom', $last['type']);
		}
		
		
		
	}

	public function test_if_arrange_columns_saves_correctly_for_user_with_some_custom_fields():void{

		UserIndexColumn::truncate();

		$company_id = $this->set_default_company();
		$c = $this->set_access('device 123');

		$columns_ar = $this->getColumnsAr();
		
		$columns_ar[1]['show'] = true;
		$columns_ar[1]['searchable'] = true;
		$columns_ar[2]['searchable'] = true;
		$counter = count($columns_ar);
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
		
		ClientsCustomField::truncate();
		$this->setCustomFieldTypes();
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

	public function test_if_index_loads_for_clients_with_user_settings_added():void{
		
		ClientsCustomField::truncate();
		$this->setCustomFieldTypes();
		$c = $this->addNewClients(20);
		
		$this->addAllCustomFields($c['company_id'], $c['headers'], 1);

		//
		$columns_ar = $this->getColumnsAr();
		$counter = count($columns_ar);
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->first();

		$showing_rows = [];
		
		for($z = 0 ; $z < count($columns_ar) ; $z++){

			$show_row_label =  $columns_ar[$z]['label'];

			if($columns_ar[$z]['label'] === 'billing_country_id'){
				$show_row_label = 'b_country_name';
			}else if($columns_ar[$z]['label'] === 'shipping_country_id'){
				$show_row_label = 's_country_name';
			}else if($columns_ar[$z]['label'] === 'industry_id'){
				$show_row_label = 'industry_name';
			}else if($columns_ar[$z]['label'] === 'company_id'){
				$show_row_label = 'company_name';
			}

			if($z%2 === 0){
				$showing_rows[] = ['label' => $show_row_label, 'text' => $columns_ar[$z]['label']];
				$columns_ar[$z]['show'] = true;
			}else{
				$columns_ar[$z]['show'] = false;
			}
			
			
		}

		array_push($columns_ar, [
			'id'						=>	($counter+=1),
			'label'						=>	$custom_fields_db->label,
			'text'						=>	$custom_fields_db->label,
			'type'						=>	'custom',
			'is_date'					=>	false,
			'searchable'				=>	true,
			'show'						=>	true,
			'clients_custom_fields_id'	=> $custom_fields_db->id
		]);
		$showing_rows[] = ['label' => General::replaceWithUnderscores($custom_fields_db->label), 'text' => General::NormalizeColumnName($custom_fields_db->label)];
		
		$response = $this->post('/api/manage-clients/save-arranged-columns', [
			'columns' 		=> $columns_ar,
			'company_id'	=> $c['company_id']
		], $c['headers']);
		
		$response->assertStatus(200);

		//
		array_push($showing_rows, [
			'label'	=>	'actions',
			'text'	=>	'Actions'
		]);

		$params = [
			'company_id'		=>	$c['company_id'],
			'default_per_page'	=>	10
		];
		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients?'. http_build_query($params));
		
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals($showing_rows, $json['table_data']['columns']);
		$this->assertEquals('id', $json['table_data']['columns'][0]['label']);
		$this->assertEquals('last_name', $json['table_data']['columns'][1]['label']);
		$this->assertEquals('website', $json['table_data']['columns'][2]['label']);
		$this->assertEquals(10, (int)count($json['table_data']['rows']));

	}

	public function test_if_index_loads_for_clients_with_global_settings_added():void{
		
		ClientsCustomField::truncate();
		$this->setCustomFieldTypes();
		$c = $this->addNewClients(20);
		
		$this->addAllCustomFields($c['company_id'], $c['headers'], 1);

		//
		$columns_ar = $this->getColumnsAr();
		$counter = count($columns_ar);
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->first();

		$showing_rows = [];
		
		for($z = 0 ; $z < count($columns_ar) ; $z++){

			$show_row_label =  $columns_ar[$z]['label'];

			if($columns_ar[$z]['label'] === 'billing_country_id'){
				$show_row_label = 'b_country_name';
			}else if($columns_ar[$z]['label'] === 'shipping_country_id'){
				$show_row_label = 's_country_name';
			}else if($columns_ar[$z]['label'] === 'industry_id'){
				$show_row_label = 'industry_name';
			}else if($columns_ar[$z]['label'] === 'company_id'){
				$show_row_label = 'company_name';
			}
			
			if($z%2 === 0){
				$showing_rows[] = ['label' => $show_row_label, 'text' => $columns_ar[$z]['label']];
				$columns_ar[$z]['show'] = true;
			}else{
				$columns_ar[$z]['show'] = false;
			}
			
			
		}

		array_push($columns_ar, [
			'id'						=>	($counter+=1),
			'label'						=>	$custom_fields_db->label,
			'text'						=>	$custom_fields_db->label,
			'type'						=>	'custom',
			'is_date'					=>	false,
			'searchable'				=>	true,
			'show'						=>	true,
			'clients_custom_fields_id'	=> $custom_fields_db->id
		]);
		$showing_rows[] = ['label' => General::replaceWithUnderscores($custom_fields_db->label), 'text' => General::NormalizeColumnName($custom_fields_db->label)];
		
		SettingsIndexColumn::truncate();

		$setting = new SettingsIndexColumn();
		$setting->company_id = $c['company_id'];
		$setting->feature_name = 'clients';
		$setting->columns_json = json_encode($columns_ar);
		$setting->created_at = now();
		$setting->updated_at = now();
		$setting->save();
		

		//
		array_push($showing_rows, [
			'label'	=>	'actions',
			'text'	=>	'Actions'
		]);

		$params = [
			'company_id'		=>	$c['company_id'],
			'default_per_page'	=>	10
		];
		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients?'. http_build_query($params));
		
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals($showing_rows, $json['table_data']['columns']);
		$this->assertEquals('id', $json['table_data']['columns'][0]['label']);
		$this->assertEquals('last_name', $json['table_data']['columns'][1]['label']);
		$this->assertEquals('website', $json['table_data']['columns'][2]['label']);
		$this->assertEquals(10, (int)count($json['table_data']['rows']));

	}

	public function test_if_index_loads_with_column_order_for_clients_with_user_settings_added():void{
		
		ClientsCustomField::truncate();
		$this->setCustomFieldTypes();
		$c = $this->addNewClients(20);
		
		$this->addAllCustomFields($c['company_id'], $c['headers'], 1);

		//
		$columns_ar = $this->getColumnsAr();
		$counter = count($columns_ar);
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->first();

		$showing_rows = [];
		
		for($z = 0 ; $z < count($columns_ar) ; $z++){

			$show_row_label =  $columns_ar[$z]['label'];

			if($columns_ar[$z]['label'] === 'billing_country_id'){
				$show_row_label = 'b_country_name';
			}else if($columns_ar[$z]['label'] === 'shipping_country_id'){
				$show_row_label = 's_country_name';
			}else if($columns_ar[$z]['label'] === 'industry_id'){
				$show_row_label = 'industry_name';
			}else if($columns_ar[$z]['label'] === 'company_id'){
				$show_row_label = 'company_name';
			}

			if($z%2 === 0){
				$showing_rows[] = ['label' => $show_row_label, 'text' => $columns_ar[$z]['label']];
				$columns_ar[$z]['show'] = true;
			}else{
				$columns_ar[$z]['show'] = false;
			}
			
			
		}

		array_push($columns_ar, [
			'id'						=>	($counter+=1),
			'label'						=>	$custom_fields_db->label,
			'text'						=>	$custom_fields_db->label,
			'type'						=>	'custom',
			'is_date'					=>	false,
			'searchable'				=>	true,
			'show'						=>	true,
			'clients_custom_fields_id'	=> $custom_fields_db->id
		]);
		$showing_rows[] = ['label' => General::replaceWithUnderscores($custom_fields_db->label), 'text' => General::NormalizeColumnName($custom_fields_db->label)];

		/* swap last to first and vice versa */
		$last_ar = end($columns_ar);
		$last_showing_row = end($showing_rows);

		$columns_ar[count($columns_ar)-1] = $columns_ar[0];
		$showing_rows[count($showing_rows)-1] = $showing_rows[0];

		$columns_ar[0] = $last_ar;
		$showing_rows[0] = $last_showing_row;

		
		$response = $this->post('/api/manage-clients/save-arranged-columns', [
			'columns' 		=> $columns_ar,
			'company_id'	=> $c['company_id']
		], $c['headers']);
		
		$response->assertStatus(200);

		//
		array_push($showing_rows, [
			'label'	=>	'actions',
			'text'	=>	'Actions'
		]);

		$params = [
			'company_id'		=>	$c['company_id'],
			'default_per_page'	=>	10
		];
		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients?'. http_build_query($params));
		
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals($showing_rows, $json['table_data']['columns']);
		$this->assertEquals('id', $json['table_data']['columns'][count($json['table_data']['columns'])-2]['label']);
		$this->assertEquals('last_name', $json['table_data']['columns'][1]['label']);
		$this->assertEquals('website', $json['table_data']['columns'][2]['label']);
		$this->assertEquals(10, (int)count($json['table_data']['rows']));

	}


	public function test_if_index_loads_with_custom_datetime_filters_for_clients_with_user_settings_added():void{
		
		ClientsCustomField::truncate();
		$this->setCustomFieldTypes();

		
		
		$c = $this->addNewClients(20, true);
		//
		$columns_ar = $this->getColumnsAr();
		$counter = count($columns_ar);
		$custom_fields_db_s = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		$showing_rows = [];
		
		for($z = 0 ; $z < count($columns_ar) ; $z++){

			$show_row_label =  $columns_ar[$z]['label'];

			if($columns_ar[$z]['label'] === 'billing_country_id'){
				$show_row_label = 'b_country_name';
			}else if($columns_ar[$z]['label'] === 'shipping_country_id'){
				$show_row_label = 's_country_name';
			}else if($columns_ar[$z]['label'] === 'industry_id'){
				$show_row_label = 'industry_name';
			}else if($columns_ar[$z]['label'] === 'company_id'){
				$show_row_label = 'company_name';
			}

			if($z%2 === 0){
				$showing_rows[] = ['label' => $show_row_label, 'text' => $columns_ar[$z]['label']];
				$columns_ar[$z]['show'] = true;
			}else{
				$columns_ar[$z]['show'] = false;
			}
			
			
		}

		foreach($custom_fields_db_s as $custom_fields_db){
			$searchable = false;
			$is_date = false;
			if($custom_fields_db->label === 'client datetime'){
				$searchable = true;
				$is_date = true;
			}
			array_push($columns_ar, [
				'id'						=>	($counter+=1),
				'label'						=>	$custom_fields_db->label,
				'text'						=>	$custom_fields_db->label,
				'type'						=>	'custom',
				'is_date'					=>	$is_date,
				'searchable'				=>	$searchable,
				'show'						=>	true,
				'clients_custom_fields_id'	=> $custom_fields_db->id
			]);
			$showing_rows[] = ['label' => General::replaceWithUnderscores($custom_fields_db->label), 'text' => General::NormalizeColumnName($custom_fields_db->label)];
		}
		
		
		/* swap last to first and vice versa */
		$last_ar = end($columns_ar);
		$last_showing_row = end($showing_rows);

		$columns_ar[count($columns_ar)-1] = $columns_ar[0];
		$showing_rows[count($showing_rows)-1] = $showing_rows[0];

		$columns_ar[0] = $last_ar;
		$showing_rows[0] = $last_showing_row;

		
		$response = $this->post('/api/manage-clients/save-arranged-columns', [
			'columns' 		=> $columns_ar,
			'company_id'	=> $c['company_id']
		], $c['headers']);
		
		$response->assertStatus(200);

		//
		array_push($showing_rows, [
			'label'	=>	'actions',
			'text'	=>	'Actions'
		]);

		$params = [
			'company_id'		=>	$c['company_id'],
			'default_per_page'	=>	10,
			'date_range'		=>	[
				'2018-01-25T00:00:00.000Z',
				'2018-01-30T00:00:00.000Z'
			]
		];
		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients?'. http_build_query($params));
		
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals($showing_rows, $json['table_data']['columns']);
		$this->assertEmpty($json['table_data']['rows']);

	}


	public function test_if_index_loads_with_custom_fields_filters_for_clients_with_user_settings_added():void{
		
		ClientsCustomField::truncate();
		$this->setCustomFieldTypes();

		
		
		$c = $this->addNewClients(20, true);
		//
		$columns_ar = $this->getColumnsAr();
		$counter = count($columns_ar);
		$custom_fields_db_s = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		$showing_rows = [];
		
		for($z = 0 ; $z < count($columns_ar) ; $z++){

			$show_row_label =  $columns_ar[$z]['label'];

			if($columns_ar[$z]['label'] === 'billing_country_id'){
				$show_row_label = 'b_country_name';
			}else if($columns_ar[$z]['label'] === 'shipping_country_id'){
				$show_row_label = 's_country_name';
			}else if($columns_ar[$z]['label'] === 'industry_id'){
				$show_row_label = 'industry_name';
			}else if($columns_ar[$z]['label'] === 'company_id'){
				$show_row_label = 'company_name';
			}

			if($z%2 === 0){
				$showing_rows[] = ['label' => $show_row_label, 'text' => $columns_ar[$z]['label']];
				$columns_ar[$z]['show'] = true;
			}else{
				$columns_ar[$z]['show'] = false;
			}
			$columns_ar[$z]['searchable'] = false;
			
		}

		foreach($custom_fields_db_s as $custom_fields_db){
			$searchable = false;
			$is_date = false;
			if($custom_fields_db->label === 'client email'){
				$searchable = true;
			}
			array_push($columns_ar, [
				'id'						=>	($counter+=1),
				'label'						=>	$custom_fields_db->label,
				'text'						=>	$custom_fields_db->label,
				'type'						=>	'custom',
				'is_date'					=>	$is_date,
				'searchable'				=>	$searchable,
				'show'						=>	true,
				'clients_custom_fields_id'	=> $custom_fields_db->id
			]);
			$showing_rows[] = ['label' => General::replaceWithUnderscores($custom_fields_db->label), 'text' => General::NormalizeColumnName($custom_fields_db->label)];
		}
		
		
		/* swap last to first and vice versa */
		$last_ar = end($columns_ar);
		$last_showing_row = end($showing_rows);

		$columns_ar[count($columns_ar)-1] = $columns_ar[0];
		$showing_rows[count($showing_rows)-1] = $showing_rows[0];

		$columns_ar[0] = $last_ar;
		$showing_rows[0] = $last_showing_row;

		
		$response = $this->post('/api/manage-clients/save-arranged-columns', [
			'columns' 		=> $columns_ar,
			'company_id'	=> $c['company_id']
		], $c['headers']);
		
		$response->assertStatus(200);

		//
		array_push($showing_rows, [
			'label'	=>	'actions',
			'text'	=>	'Actions'
		]);

		$params = [
			'company_id'		=>	$c['company_id'],
			'default_per_page'	=>	10,
			'date_range'		=>	[
				'2018-01-25T00:00:00.000Z',
				'2018-01-30T00:00:00.000Z'
			],
			'searched_term'	=>	'bla email'
		];
		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients?'. http_build_query($params));
		
		$response->assertStatus(200);
		$json = $response->json();
		
		$this->assertEquals($showing_rows, $json['table_data']['columns']);
		$this->assertEmpty($json['table_data']['rows']);

	}


	public function test_if_index_loads_with_custom_field_sorting_for_clients_with_user_settings_added():void{
		
		ClientsCustomField::truncate();
		$this->setCustomFieldTypes();

		
		
		$c = $this->addNewClients(20, true);
		//
		$columns_ar = $this->getColumnsAr();
		$counter = count($columns_ar);
		$custom_fields_db_s = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		$showing_rows = [];
		
		for($z = 0 ; $z < count($columns_ar) ; $z++){

			$show_row_label =  $columns_ar[$z]['label'];

			if($columns_ar[$z]['label'] === 'billing_country_id'){
				$show_row_label = 'b_country_name';
			}else if($columns_ar[$z]['label'] === 'shipping_country_id'){
				$show_row_label = 's_country_name';
			}else if($columns_ar[$z]['label'] === 'industry_id'){
				$show_row_label = 'industry_name';
			}else if($columns_ar[$z]['label'] === 'company_id'){
				$show_row_label = 'company_name';
			}

			if($z%2 === 0){
				$showing_rows[] = ['label' => $show_row_label, 'text' => $columns_ar[$z]['label']];
				$columns_ar[$z]['show'] = true;
			}else{
				$columns_ar[$z]['show'] = false;
			}
			$columns_ar[$z]['searchable'] = false;
			
		}

		foreach($custom_fields_db_s as $custom_fields_db){
			$searchable = false;
			$is_date = false;
			
			array_push($columns_ar, [
				'id'						=>	($counter+=1),
				'label'						=>	$custom_fields_db->label,
				'text'						=>	$custom_fields_db->label,
				'type'						=>	'custom',
				'is_date'					=>	$is_date,
				'searchable'				=>	$searchable,
				'show'						=>	true,
				'clients_custom_fields_id'	=> $custom_fields_db->id
			]);
			$showing_rows[] = ['label' => General::replaceWithUnderscores($custom_fields_db->label), 'text' => General::NormalizeColumnName($custom_fields_db->label)];
		}
		
		
		/* swap last to first and vice versa */
		$last_ar = end($columns_ar);
		$last_showing_row = end($showing_rows);

		$columns_ar[count($columns_ar)-1] = $columns_ar[0];
		$showing_rows[count($showing_rows)-1] = $showing_rows[0];

		$columns_ar[0] = $last_ar;
		$showing_rows[0] = $last_showing_row;

		
		$response = $this->post('/api/manage-clients/save-arranged-columns', [
			'columns' 		=> $columns_ar,
			'company_id'	=> $c['company_id']
		], $c['headers']);
		
		$response->assertStatus(200);

		//
		array_push($showing_rows, [
			'label'	=>	'actions',
			'text'	=>	'Actions'
		]);
		
		$params = [
			'company_id'		=>	$c['company_id'],
			'default_per_page'	=>	10,
			'type'			=>	'sort',
			'searched_term'	=>	'bla email',
			'current_page'	=>	1,
			'sorted_column'	=>	[
				'label'				=>	'id',
				'text'				=>	'Id',
				'sort_visibility'	=>	'desc'
			]
		];
		
		$response = $this->withHeaders($c['headers'])->get('/api/manage-clients?'. http_build_query($params));
		
		$response->assertStatus(200);
		
		$json = $response->json();
		
		$this->assertEquals(20, $json['table_data']['rows'][0]['id']);
		$this->assertEquals($showing_rows, $json['table_data']['columns']);
		$this->assertNotEmpty($json['table_data']['rows']);

	}


	

}
