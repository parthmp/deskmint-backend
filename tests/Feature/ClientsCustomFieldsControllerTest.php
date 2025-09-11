<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\AccessTokenData;
use App\Models\ClientsCustomField;
use App\Models\Company;
use App\Models\CustomFieldType;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\ManageFlatTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;
use Tests\Traits\CustomFields;

class ClientsCustomFieldsControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;
	
	public function test_if_it_can_fetch_field_types() : void{

		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::factory()->count(15)->create();

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/clients-custom-fields/fetch-field-types?' . $queryParams);

		
		$response->assertStatus(200);
		$json = $response->json();
		$this->assertEquals(15, count($json));

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data() : void{

		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->count(15)->create();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		'',
			'label'					=>		'',
			'is_required'			=>		'',
			'add_edit_page_order'	=>		'',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data2() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->count(15)->create();

		$response = $this->post('/api/clients-custom-fields', [
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data3() : void{

		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->count(15)->create();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		'',
			'label'					=>		'123',
			'is_required'			=>		'',
			'add_edit_page_order'	=>		'456',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_input_field_id() : void{

		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->count(15)->create();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		'20',
			'label'					=>		'test label',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'company_id'			=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data_for_select_and_multiselect() : void{
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'select'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'select')->first();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'test label',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'company_id'			=>		$company_id
		],  $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

		$this->assertFalse(Schema::hasColumn('clients_flat', 'test_label'));

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data_for_select_and_multiselect_2() : void{

		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'multiselect'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'multiselect')->first();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'test label',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'  ',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

		$this->assertFalse(Schema::hasColumn('clients_flat', 'test_label'));

	}

	public function test_if_creating_new_client_custom_field_success_1() : void{

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'multiselect'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'multiselect')->first();

		ClientsCustomField::truncate();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'test label',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		$added_field = ClientsCustomField::where('label', '=', 'test label')->first();
		$this->assertNotEmpty($added_field);

		$this->assertTrue(Schema::hasColumn('clients_flat', 'test_label'));

	}

	public function test_if_creating_new_client_custom_field_success_2() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'select'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'select')->first();

		ClientsCustomField::truncate();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'test label',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		$added_field = ClientsCustomField::where('label', '=', 'test label')->first();
		$this->assertNotEmpty($added_field);

		$this->assertTrue(Schema::hasColumn('clients_flat', 'test_label'));

	}

	public function test_if_creating_new_client_custom_field_success_3() : void{

		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();

		ClientsCustomField::truncate();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'test label email',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		$added_field = ClientsCustomField::where('label', '=', 'test label email')->first();
		$this->assertNotEmpty($added_field);

		$this->assertTrue(Schema::hasColumn('clients_flat', 'test_label_email'));

	}

	private function getQuery($device, $queryParams, $url = '/api/clients-custom-fields?'){

		$c = $this->set_access($device);

		$response = $this->withHeaders($c['headers'])->get($url . $queryParams);

		return $response;

	}

	public function test_if_table_does_not_load_because_of_empty(){

		$device = 'device 123';

		$company_id = $this->set_default_company();

		ClientsCustomField::truncate();
				
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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$company_id = $this->set_default_company();
		
		ClientsCustomField::truncate();
		ClientsCustomField::factory()->count(50)->create([
			'company_id'	=>	$company_id
		]);
		
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

		ClientsCustomField::truncate();
		ClientsCustomField::factory()->count(50)->create([
			'company_id'	=>	$company_id
		]);
		

		ClientsCustomField::factory()->create([
			'label'			=>	'BLATEST123',
			'company_id'	=>	$company_id
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

		ClientsCustomField::truncate();

		ClientsCustomField::factory()->create([
			'company_id'			=>	$company_id,
			'label'					=>	'test label',
			'created_at'			=>	'2025-08-11 12:15:05',
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15,
			'searched_term'		=>	'test lab123'
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
		ClientsCustomField::truncate();

		for ($z = 1; $z <= 50; $z++) {
			ClientsCustomField::factory()->create([
				'company_id' 	=> $company_id,
				'label' 		=> 'bla'.$z
			]);
		}
		
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
		$this->assertEquals('bla48', $response['table_data']['rows'][0]['label']);
		$this->assertEquals(2, (int)$response['current_page']);

	}

	public function test_if_fetching_fails_with_invalid_clients_custom_field_id() : void{

		
		$device = 'device 123';

		$company_id = $this->set_default_company();

		$c = $this->set_access($device);

		ClientsCustomField::truncate();

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/clients-custom-fields/200?' . $queryParams);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_fetching_success_with_valid_clients_custom_field_id() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		ClientsCustomField::truncate();

		ClientsCustomField::factory()->create([
			'id'			=>	500,
			'company_id'	=>	$company_id
		]);

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/clients-custom-fields/500?' . $queryParams);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertNotEmpty($json);
		$this->assertNotEmpty($json['custom_field_type']);

	}
	
	/**/

	public function test_if_updating_new_client_custom_field_fails_invalid_id() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id
		]);

		$response = $this->patch('/api/clients-custom-fields/200', [
			'input_field'				=>		'',
			'label'						=>		'',
			'is_required'				=>		'',
			'show_on_index'				=>		'',
			'add_edit_page_order'		=>		'',
			'column_order'				=>		'',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_updating_new_client_custom_field_fails_invalid_data() : void{

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id
		]);

		$response = $this->patch('/api/clients-custom-fields/150', [
			'input_field'				=>		'',
			'label'						=>		'',
			'is_required'				=>		'',
			'show_on_index'				=>		'',
			'add_edit_page_order'		=>		'',
			'column_order'				=>		'',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}
	
	public function test_if_updating_new_client_custom_field_fails_invalid_data2() : void{

		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id
		]);

		$response = $this->patch('/api/clients-custom-fields/150', [
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}
	
	public function test_if_updating_new_client_custom_field_fails_invalid_data3() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id
		]);

		$response = $this->patch('/api/clients-custom-fields/150', [
			'input_field'			=>		'',
			'label'					=>		'123',
			'is_required'			=>		'',
			'show_on_index'			=>		'',
			'add_edit_page_order'	=>		'456',
			'column_order'			=>		'',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	
	public function test_if_updating_new_client_custom_field_fails_invalid_input_field_id() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id
		]);

		$response = $this->patch('/api/clients-custom-fields/150', [
			'input_field'			=>		'20',
			'label'					=>		'test label',
			'is_required'			=>		'true',
			'show_on_index'			=>		'false',
			'add_edit_page_order'	=>		'5',
			'column_order'			=>		'10',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	
	public function test_if_updating_new_client_custom_field_fails_with_invalid_data_for_select_multiselect() : void{


		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'id'			=>		10,
			'input_type'	=>		'select'
		]);

		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id
		]);

		$select_field = CustomFieldType::where('id', '=', 10)->first();

		$response = $this->patch('/api/clients-custom-fields/150', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'test label here',
			'past_label'			=>		'past label here',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	
	public function test_if_updating_new_client_custom_field_fails_invalid_data_for_select_and_multiselect_2() : void{

		
		$device = 'device 123';
		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'id'			=>		10,
			'input_type'	=>		'multiselect'
		]);

		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'multiselect')->first();

		$response = $this->patch('/api/clients-custom-fields/150', [
			'input_field'			=>		$select_field->id,
			'past_label'			=>		'past test label',
			'label'					=>		'test label',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'  ',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	
	public function test_if_updating_new_client_custom_field_success_1() : void{

		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$flat_table = new ManageFlatTable('clients_flat', 'clients', 'client_id');
		$flat_table->addFlatTableColumn('past label here');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'id'			=>		10,
			'input_type'	=>		'multiselect'
		]);

		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id,
			'label'			=>	'before update'
		]);

		$this->assertFalse(Schema::hasColumn('clients_flat', 'after_update_label'));

		$select_field = CustomFieldType::where('input_type', '=', 'multiselect')->first();

		$response = $this->patch('/api/clients-custom-fields/150', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'after update label',
			'past_label'			=>		'past label here',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('updated_success', $response['validity']);

		$updated_field = ClientsCustomField::where('label', '=', 'before update')->first();
		$this->assertEmpty($updated_field);

		$updated_field = ClientsCustomField::where('label', '=', 'after update label')->first();
		$this->assertNotEmpty($updated_field);

		/* test for flat table */
		$this->assertTrue(Schema::hasColumn('clients_flat', 'after_update_label'));

	}
	
	public function test_if_updating_new_client_custom_field_success_2() : void{

		$device = 'device 123';

		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'id'			=>		10,
			'input_type'	=>		'multiselect'
		]);

		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id,
			'label'			=>	'before update'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'multiselect')->first();

		Schema::dropIfExists('clients_flat');

		$flat_table = new ManageFlatTable('clients_flat', 'clients', 'client_id');
		$flat_table->addFlatTableColumn('past label here');
		$this->assertFalse(Schema::hasColumn('clients_flat', 'after_update'));

		$response = $this->patch('/api/clients-custom-fields/150', [
			'input_field'			=>		$select_field->id,
			'past_label'			=>		'past label here',
			'label'					=>		'after update',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('updated_success', $response['validity']);

		$updated_field = ClientsCustomField::where('label', '=', 'before update')->first();
		$this->assertEmpty($updated_field);

		$updated_field = ClientsCustomField::where('label', '=', 'after update')->first();
		$this->assertNotEmpty($updated_field);

		$this->assertTrue(Schema::hasColumn('clients_flat', 'after_update'));

	}
	
	public function test_if_updating_new_client_custom_field_success_3() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'							=>	150,
			'company_id'					=>	$company_id,
			'label'							=>	'before update'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();

		$flat_table = new ManageFlatTable('clients_flat', 'clients', 'client_id');
		$flat_table->addFlatTableColumn('past label here');
		$this->assertFalse(Schema::hasColumn('clients_flat', 'after_update'));
		
		$response = $this->put('/api/clients-custom-fields/150', [
			'input_field'			=>		$select_field->id,
			'past_label'			=>		'past label here',
			'label'					=>		'after update',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('updated_success', $response['validity']);

		$updated_field = ClientsCustomField::where('label', '=', 'before update')->first();
		$this->assertEmpty($updated_field);

		$updated_field = ClientsCustomField::where('label', '=', 'after update')->first();
		$this->assertNotEmpty($updated_field);

		$this->assertTrue(Schema::hasColumn('clients_flat', 'after_update'));

	}

	public function test_if_it_fails_if_label_length_greater_than_allowed() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'							=>	150,
			'company_id'					=>	$company_id,
			'label'							=>	'before update'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();
		
		$response = $this->put('/api/clients-custom-fields/150', [
			'input_field'			=>		$select_field->id,
			'past_label'			=>		'past label here',
			'label'					=>		'very very very very very very very very very long label',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_label', $response['validity']);

		
	}

	/**/

	public function test_if_label_already_exists_fails_to_save() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'							=>	150,
			'company_id'					=>	$company_id,
			'label'							=>	'sample label here'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();
		
		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'sample label here',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_label', $response['validity']);

	}

	public function test_if_label_already_exists_fails_to_update() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'							=>	150,
			'company_id'					=>	$company_id,
			'label'							=>	'sample label here'
		]);

		ClientsCustomField::factory()->create([
			'id'							=>	151,
			'company_id'					=>	$company_id,
			'label'							=>	'sample label here 2'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();
		
		$response = $this->patch('/api/clients-custom-fields/151', [
			'input_field'			=>		$select_field->id,
			'past_label'			=>		'sample label here 2',
			'label'					=>		'sample label here',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_label', $response['validity']);

	}


	public function test_if_label_has_invalid_chars_fails_to_save() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();
		// ClientsCustomField::factory()->create([
		// 	'id'							=>	150,
		// 	'company_id'					=>	$company_id,
		// 	'label'							=>	'sample label here'
		// ]);

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();
		
		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'sample label here @',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_label_chars', $response['validity']);

	}

	public function test_if_label_has_invalid_chars_fails_to_update() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();
		ClientsCustomField::factory()->create([
			'id'							=>	150,
			'company_id'					=>	$company_id,
			'label'							=>	'sample label here'
		]);

		ClientsCustomField::factory()->create([
			'id'							=>	151,
			'company_id'					=>	$company_id,
			'label'							=>	'sample label here 2'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();
		
		$response = $this->patch('/api/clients-custom-fields/151', [
			'input_field'			=>		$select_field->id,
			'past_label'			=>		'sample label here 2',
			'label'					=>		'sample label here $%^#%@<>',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_label_chars', $response['validity']);

	}

	public function test_custom_fields_column_data_types() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		CustomFieldType::truncate();

		$company_id = $this->set_default_company();
		$this->setCustomFieldTypes();

		ClientsCustomField::truncate();

		$field_types = CustomFieldType::all();
		$order = 1;
		$column_names = [];
		$column_types = [];
		foreach($field_types as $field_type){

			$options = '';
			
			if($field_type->input_type === config('global.field_types')[3]){
				$options = "one,two,three";
			}

			if($field_type->input_type === config('global.field_types')[9]){
				$options = "five,six,seven";
			}

			$label = 'client '.$field_type->input_type;

			array_push($column_names, General::replaceWithUnderscores($label));
			array_push($column_types, $field_type->input_type);

			$response = $this->post('/api/clients-custom-fields', [
				'input_field'			=>		$field_type->id,
				'label'					=>		$label,
				'is_required'			=>		'true',
				'add_edit_page_order'	=>		$order,
				'select_options'		=>		$options,
				'company_id'			=>		$company_id
			], $c['headers']);
			
			$order++;
			$response->assertStatus(200);
			$this->assertArrayHasKey('validity', $response);
			$this->assertEquals('created_success', $response['validity']);

		}

		/* validate input types in clients_flat for each column */

		for($z = 0 ; $z < count($column_names) ; $z++){
			
			$this->assertTrue(Schema::hasColumn('clients_flat', $column_names[$z]));
			$column_type_db = Schema::getColumnType('clients_flat', $column_names[$z]);
			if($column_types[$z] === config('global.field_types')[0]){ /* text */
				$this->assertEquals('text', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[1]){ /* textarea */
				$this->assertEquals('text', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[2]){ /* email */
				$this->assertEquals('varchar', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[3]){ /* select */
				$this->assertEquals('varchar', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[4]){ /* number */
				$this->assertEquals('integer', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[5]){ /* date */
				$this->assertEquals('datetime', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[6]){ /* time */
				$this->assertEquals('varchar', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[7]){ /* datetime */
				$this->assertEquals('datetime', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[8]){ /* telephone */
				$this->assertEquals('varchar', $column_type_db);
			}else if($column_types[$z] === config('global.field_types')[9]){ /* multiselect */
				$this->assertEquals('text', $column_type_db);
			}
		}
		
	}

	public function test_deletion_without_ids_provided_1() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();
		// ClientsCustomField::factory()->create([
		// 	'id'							=>	150,
		// 	'company_id'					=>	$company_id,
		// 	'label'							=>	'sample label here'
		// ]);

		// ClientsCustomField::factory()->create([
		// 	'id'							=>	151,
		// 	'company_id'					=>	$company_id,
		// 	'label'							=>	'sample label here 2'
		// ]);

		//$select_field = CustomFieldType::where('input_type', '=', 'email')->first();
		
		$response = $this->delete('/api/clients-custom-fields', [
			'ids' => [],
			'company_id' => $company_id
		], $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}

	public function test_deletion_without_ids_provided_2() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();
		
		$response = $this->delete('/api/clients-custom-fields', [
			'company_id' => $company_id
		], $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}

	public function test_deletion_with_one_id_provided() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);
		
		ClientsCustomField::truncate();

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();
		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'test label email',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'company_id'			=>		$company_id
		], $c['headers']);
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);
		$this->assertTrue(Schema::hasColumn('clients_flat', General::replaceWithUnderscores('test label email')));
		
		$c_field = ClientsCustomField::first();

		$response = $this->delete('/api/clients-custom-fields', [
			'ids' => [$c_field->id],
			'company_id' => $company_id
		], $c['headers']);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);
		
		$this->assertFalse(Schema::hasColumn('clients_flat', General::replaceWithUnderscores('test label email')));

	}

	public function test_deletion_with_multiple_ids_provided() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('clients_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'date'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'datetime'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'textarea'
		]);
		
		ClientsCustomField::truncate();

		$custom_field_types = CustomFieldType::all();

		$labels = [];

		foreach($custom_field_types as $c_field_type){

			$label = 'test client '.$c_field_type->input_type;

			$labels[] = $label;

			$response = $this->post('/api/clients-custom-fields', [
				'input_field'			=>		$c_field_type->id,
				'label'					=>		$label,
				'is_required'			=>		'true',
				'add_edit_page_order'	=>		'5',
				'company_id'			=>		$company_id
			], $c['headers']);
			$response->assertStatus(200);
			$this->assertArrayHasKey('validity', $response);
			$this->assertEquals('created_success', $response['validity']);
			$this->assertTrue(Schema::hasColumn('clients_flat', General::replaceWithUnderscores($label)));
		}

		
		
		$custom_fields = ClientsCustomField::all();

		$ids = [];
		foreach($custom_fields as $c_field){
			array_push($ids, $c_field->id);
		}

		$response = $this->delete('/api/clients-custom-fields', [
			'ids' => $ids,
			'company_id' => $company_id
		], $c['headers']);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$deleted_fields = ClientsCustomField::whereIn('ids', $ids)->get();

		$this->assertEmpty($deleted_fields);
		
		foreach($labels as $label){
			$this->assertFalse(Schema::hasColumn('clients_flat', General::replaceWithUnderscores($label)));
		}

	}



}
