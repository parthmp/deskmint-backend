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

class ClientsCustomFieldsControllerTest extends TestCase{

	use RefreshDatabase;

    private function set_access(User $user, string $device) :Array{

		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(3599)
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	(now())->subSeconds(100)
		]);

		return [
			'token'				=>		$plain_text_token,
			'refresh_token'		=>		$refresh_token_hash
		];

	}

	private function set_default_company() : int{

		$company = Company::factory()->create([
			'company_name' 	=>  'ABC Company',
			'default'		=>	1
		]);

		return $company->id;

	}

	public function test_if_it_can_fetch_field_types() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		CustomFieldType::factory()->count(15)->create();

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
		])->get('/api/clients-custom-fields/fetch-field-types?' . $queryParams);

		
		$response->assertStatus(200);
		$json = $response->json();
		$this->assertEquals(15, count($json));

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->count(15)->create();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		'',
			'label'					=>		'',
			'is_required'			=>		'',
			'add_edit_page_order'	=>		'',
			'company_id'				=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data2() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->count(15)->create();

		$response = $this->post('/api/clients-custom-fields', [
			'company_id'				=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data3() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->count(15)->create();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		'',
			'label'					=>		'123',
			'is_required'			=>		'',
			'add_edit_page_order'	=>		'456',
			'company_id'				=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_input_field_id() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->count(15)->create();

		$response = $this->post('/api/clients-custom-fields', [
			'input_field'			=>		'20',
			'label'					=>		'test label',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'company_id'			=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data_for_select_and_multiselect() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

		$this->assertFalse(Schema::hasColumn('clients_flat', 'test_label'));

	}

	public function test_if_creating_new_client_custom_field_fails_invalid_data_for_select_and_multiselect_2() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

		$this->assertFalse(Schema::hasColumn('clients_flat', 'test_label'));

	}

	public function test_if_creating_new_client_custom_field_success_1() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		$added_field = ClientsCustomField::where('label', '=', 'test label')->first();
		$this->assertNotEmpty($added_field);

		$this->assertTrue(Schema::hasColumn('clients_flat', 'test_label'));

	}

	public function test_if_creating_new_client_custom_field_success_2() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		$added_field = ClientsCustomField::where('label', '=', 'test label')->first();
		$this->assertNotEmpty($added_field);

		$this->assertTrue(Schema::hasColumn('clients_flat', 'test_label'));

	}

	public function test_if_creating_new_client_custom_field_success_3() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		$added_field = ClientsCustomField::where('label', '=', 'test label email')->first();
		$this->assertNotEmpty($added_field);

		$this->assertTrue(Schema::hasColumn('clients_flat', 'test_label_email'));

	}

	private function getQuery($token, $refresh_token, $device, $queryParams, $url = '/api/clients-custom-fields?'){

		$response = $this->withHeaders([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
		])->get($url . $queryParams);

		return $response;

	}

	public function test_if_table_does_not_load_because_of_empty(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		ClientsCustomField::truncate();
				
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);

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

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();
		
		ClientsCustomField::truncate();
		ClientsCustomField::factory()->count(50)->create([
			'company_id'	=>	$company_id
		]);
		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_table_filters_for_searched_term(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_table_filters_for_searched_term_not_matched(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_table_filters_for_current_page(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);

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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		ClientsCustomField::truncate();

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
		])->get('/api/clients-custom-fields/200?' . $queryParams);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_fetching_success_with_valid_clients_custom_field_id() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		ClientsCustomField::truncate();

		ClientsCustomField::factory()->create([
			'id'			=>	500,
			'company_id'	=>	$company_id
		]);

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
		])->get('/api/clients-custom-fields/500?' . $queryParams);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertNotEmpty($json);
		$this->assertNotEmpty($json['custom_field_type']);

	}
	
	/**/

	public function test_if_updating_new_client_custom_field_fails_invalid_id() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_updating_new_client_custom_field_fails_invalid_data() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}
	
	public function test_if_updating_new_client_custom_field_fails_invalid_data2() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		ClientsCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id
		]);

		$response = $this->patch('/api/clients-custom-fields/150', [
			'company_id'				=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}
	
	public function test_if_updating_new_client_custom_field_fails_invalid_data3() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	
	public function test_if_updating_new_client_custom_field_fails_invalid_input_field_id() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	
	public function test_if_updating_new_client_custom_field_fails_with_invalid_data_for_select_multiselect() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	
	public function test_if_updating_new_client_custom_field_fails_invalid_data_for_select_and_multiselect_2() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	
	public function test_if_updating_new_client_custom_field_success_1() : void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);
		
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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);
		
		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_label', $response['validity']);

		
	}

	/**/
}
