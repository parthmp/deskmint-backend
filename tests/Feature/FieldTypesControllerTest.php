<?php

namespace Tests\Feature;


use App\Models\CustomFieldType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class FieldTypesControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

    public function test_if_it_can_fetch_custom_field_types(): void{
       
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-field-types/fetch-input-types?' . $queryParams);

		
		$response->assertStatus(200);

		$response = $response->json();

		$this->assertEquals(count(config('global.field_types')), count($response));

    }


	public function test_if_adding_input_type_fails_1(){

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-field-types', [
			'input_type'				=>	'',
			'input_name'				=>	'',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(2, count($json['errors']));
	}

	public function test_if_adding_input_type_fails_2(){

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-field-types', [
			'input_type'				=>	'something',
			'input_name'				=>	'testname',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_field', $response['validity']);

	}

	public function test_if_adding_input_type_succeeded(){

		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		$response = $this->post('/api/manage-field-types', [
			'input_type'				=>	'datetime',
			'input_name'				=>	'test datetime field',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		/* check db */

		$field = CustomFieldType::where('input_name', '=', 'test datetime field')->first();

		$this->assertNotEmpty($field);
		
	}

	private function getQuery($device, $queryParams, $url = '/api/manage-field-types?'){

		$c = $this->set_access($device);

		$response = $this->withHeaders($c['headers'])->get($url . $queryParams);

		return $response;

	}

	public function test_if_table_does_not_load_because_of_empty(){

	
		$device = 'device 123';

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
				
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15
		]);

		$response = $this->getQuery($device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_table_loads_index(){

	
		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create();
		}

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15
		]);

		$response = $this->getQuery($device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_table_filters_for_searched_term(){

		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create();
		}

		CustomFieldType::factory()->create([
			'input_name'	=>	'BLATEST123'
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15,
			'searched_term'		=>	'BLATEST'
		]);

		$response = $this->getQuery($device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_table_filters_for_searched_term_not_matched(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create();
		}

		CustomFieldType::factory()->create([
			'input_name'	=>	'BLATEST123'
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15,
			'searched_term'		=>	'BLATEST4'
		]);

		$response = $this->getQuery($device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_table_filters_for_current_page(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLA'.$z
			]);
		}

		CustomFieldType::factory()->create([
			'input_name'	=>	'BLATEST123'
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	2,
			'current_page'		=>	2
		]);

		$response = $this->getQuery($device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		/* check if it is really on second page, it is descending id by default now */
		$this->assertEquals('BLA8', $response['table_data']['rows'][0]['input_name']);
		$this->assertEquals(2, (int)$response['current_page']);

	}

	public function test_if_table_filters_with_per_page(){

		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLA'.$z
			]);
		}

		CustomFieldType::factory()->create([
			'input_name'	=>	'BLATEST123'
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	2,
			'per_page'			=>	5,
			'current_page'		=>	2
		]);

		$response = $this->getQuery($device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));
		
	}

	

	public function test_if_page_shows_page_1_for_current_page_search_term(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}

		CustomFieldType::factory()->create([
			'input_name'	=>	'BLATEST123'
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	2,
			'per_page'			=>	5,
			'searched_term'		=>	'BLABLA'
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(1, (int)$response['current_page']);

	}

	public function test_if_page_shows_page_1_for_per_page(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}

		CustomFieldType::factory()->create([
			'input_name'	=>	'BLATEST123'
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	2,
			'per_page'			=>	5
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(1, (int)$response['current_page']);

	}

	/* Check if column sorts, asc and desc */
	public function test_if_column_sorts_asc(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}

		CustomFieldType::factory()->create([
			'input_name'	=>	'BLATEST123'
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	2,
			'per_page'			=>	5,
			'sorted_column'		=>	[
				'label'					=>		'input_name',
				'sort_visibility'		=>		'asc'
			]
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(1, (int)$response['current_page']);

		for($z = 0 ; $z < 5 ; $z++){
			$this->assertEquals('BLABLA'.$z, $response['table_data']['rows'][$z]['input_name']);
		}
		
		

	}

	public function test_if_column_sorts_asc_with_current_page(){

		$device = 'device 123';
		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		
		/* add fake data */
		for($z = 11 ; $z < 33 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}
		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	5,
			'per_page'			=>	5,
			'current_page'		=>	2,
			'sorted_column'		=>	[
				'label'					=>		'input_name',
				'sort_visibility'		=>		'asc'
			]
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(2, (int)$response['current_page']);
		
		for($z = 16 ; $z < 21 ; $z++){
			$this->assertEquals('BLABLA'.$z, $response['table_data']['rows'][($z-16)]['input_name']);
		}
		
		

	}

	public function test_if_column_sorts_desc(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}

		CustomFieldType::factory()->create([
			'input_name'	=>	'BLATEST123'
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	2,
			'per_page'			=>	5,
			'sorted_column'		=>	[
				'label'					=>		'input_name',
				'sort_visibility'		=>		'desc'
			]
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(1, (int)$response['current_page']);

		for($z = 5 ; $z > 10 ; $z++){
			$this->assertEquals('BLABLA'.$z, $response['table_data']['rows'][$z]['input_name']);
		}
		
		

	}

	
	public function test_if_column_sorts_desc_with_current_page(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		
		/* add fake data */
		for($z = 11 ; $z < 33 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}
		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	5,
			'per_page'			=>	5,
			'current_page'		=>	2,
			'sorted_column'		=>	[
				'label'					=>		'input_name',
				'sort_visibility'		=>		'desc'
			]
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(2, (int)$response['current_page']);
		
		$index = 0;
		for($z = 27 ; $z >= 23 ; $z--){
			$this->assertEquals('BLABLA'.$z, $response['table_data']['rows'][$index]['input_name']);
			$index++;
		}
		
		

	}


	public function test_if_column_sorts_desc_with_per_page_search_term_and_current_page(){

		$device = 'device 123';

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		
		/* add fake data */
		for($z = 11 ; $z < 33 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}

		/* add fake data */
		for($z = 55 ; $z < 66 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'YAYBLA'.$z
			]);
		}
		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	5,
			'per_page'			=>	5,
			'current_page'		=>	2,
			'searched_term'		=>	'YaY',
			'sorted_column'		=>	[
				'label'					=>		'input_name',
				'sort_visibility'		=>		'desc'
			]
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(2, (int)$response['current_page']);
		
		$index = 0;
		for($z = 60 ; $z >= 56 ; $z--){
			$this->assertEquals('YAYBLA'.$z, $response['table_data']['rows'][$index]['input_name']);
			$index++;
		}

	}

	public function test_if_column_sorts_asc_with_per_page_search_term_and_current_page(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		
		/* add fake data */
		for($z = 11 ; $z < 33 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}

		/* add fake data */
		for($z = 55 ; $z < 66 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'YAYBLA'.$z
			]);
		}
		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	5,
			'per_page'			=>	5,
			'current_page'		=>	2,
			'searched_term'		=>	'YaY',
			'sorted_column'		=>	[
				'label'					=>		'input_name',
				'sort_visibility'		=>		'asc'
			]
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(2, (int)$response['current_page']);
		
		$index = 0;
		for($z = 60 ; $z < 65 ; $z++){
			$this->assertEquals('YAYBLA'.$z, $response['table_data']['rows'][$index]['input_name']);
			$index++;
		}

	}


	public function test_if_user_tries_sql_injection_by_adding_non_existant_column_label(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		
		/* add fake data */
		for($z = 11 ; $z < 33 ; $z++){
			CustomFieldType::factory()->create([
				'input_name'	=>	'BLABLA'.$z
			]);
		}
		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	5,
			'per_page'			=>	5,
			'current_page'		=>	2,
			'sorted_column'		=>	[
				'label'					=>		'malicious_column_name',
				'sort_visibility'		=>		'desc'
			]
		]);

		$response = $this->getQuery($device, $queryParams);
		

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));

		$this->assertEquals(2, (int)$response['current_page']);
		
		$index = 0;
		for($z = 27 ; $z < 24 ; $z--){
			/* should have no effect */
			$this->assertEquals('BLABLA'.$z, $response['table_data']['rows'][$index]['input_name']);
			$index++;
		}

	}


	public function test_if_fetching_field_type_fails_with_invalid_id(){
		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		$queryParams = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->getQuery($device, $queryParams, '/api/manage-field-types/100?');

		$response->assertStatus((int)config('global.error_code'));
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);
		

	}

	public function test_if_fetching_field_type_succeeded_with_valid_id(){

		$device = 'device 123';

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		CustomFieldType::factory()->create();

		$queryParams = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->getQuery($device, $queryParams, '/api/manage-field-types/1?');

		$response->assertStatus(200);
		
		$json = $response->json();
		
		$this->assertArrayHasKey('id', $json);
		$this->assertEquals(1, (int)$json['id']);

	}

	public function test_if_update_fails_with_invalid_data(){

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		
		$response = $this->patch('/api/manage-field-types/100', [
			'input_type'	=>	'',
			'input_name'	=>	'',
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(2, count($json['errors']));

	}

	public function test_if_update_fails_with_invalid_data_2(){
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		$field_temp = CustomFieldType::factory()->create();
		
		$response = $this->patch('/api/manage-field-types/'.$field_temp->id, [
			'input_type'	=>	'',
			'input_name'	=>	'test',
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(1, count($json['errors']));

	}

	public function test_if_update_fails_with_invalid_data_3(){
		
		$device = 'device 123';
		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		$field_temp = CustomFieldType::factory()->create();
		
		$response = $this->patch('/api/manage-field-types/'.$field_temp->id, [
			'input_type'	=>	'bla',
			'input_name'	=>	'',
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(1, count($json['errors']));

	}


	public function test_if_update_succeeded_with_valid_data(){
		
		$device = 'device 123';
		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		$field_temp = CustomFieldType::factory()->create();
		
		$response = $this->patch('/api/manage-field-types/'.$field_temp->id, [
			'input_type'	=>	'datetime',
			'input_name'	=>	'blatesthere',
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('updated_success', $response['validity']);

		/* now check if data actually updated */

		$field = CustomFieldType::where('id', '=', $field_temp->id)->first();
		$this->assertEquals('blatesthere', $field->input_name);
		$this->assertEquals('datetime', $field->input_type);


	}

	
	public function test_if_delete_fails_with_invalid_data(){
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-field-types', [
			'ids'			=>	'',
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}
	
	public function test_if_delete_fails_with_non_numeric_data(){
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		$response = $this->delete('/api/manage-field-types', [
			'ids'			=>	[
				'bla', 'whatever'
			],
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}

	public function test_if_delete_succeeded_with_valid_data(){
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		$to_be_deleted_ids = [];
		$to_not_to_be_deleted_ids = [];

		for($z = 0 ; $z < 10 ; $z++){

			$temp_field = CustomFieldType::factory()->create([
				'id'	=>	$z
			]);

			if($z < 6){
				$to_be_deleted_ids[] = $temp_field->id;
			}else{
				$to_not_to_be_deleted_ids[] = $temp_field->id;
			}

		}
		
		/* delete ids */
		$response = $this->delete('/api/manage-field-types', [
			'ids'			=>	$to_be_deleted_ids,
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		/* verify deletion */
		$deleted_ones = CustomFieldType::whereIn('id', $to_be_deleted_ids)->get();
		$this->assertEmpty($deleted_ones);

		$not_deleted_ones = CustomFieldType::whereIn('id', $to_not_to_be_deleted_ids)->get();
		$this->assertNotEmpty($not_deleted_ones);

	}


}
