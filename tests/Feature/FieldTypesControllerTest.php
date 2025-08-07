<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Company;
use App\Models\CustomFieldType;
use App\Models\RefreshToken;
use App\Models\User;
use Database\Factories\CustomFieldTypeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FieldTypesControllerTest extends TestCase{

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
	

    public function test_if_it_can_fetch_custom_field_types(): void{
       
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
		])->get('/api/manage-field-types/fetch-input-types?' . $queryParams);

		
		$response->assertStatus(200);

		$response = $response->json();

		$this->assertEquals(count(config('global.field_types')), count($response));

    }


	public function test_if_adding_input_type_fails_1(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-field-types', [
			'input_type'				=>	'',
			'input_name'				=>	'',
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

	public function test_if_adding_input_type_fails_2(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-field-types', [
			'input_type'				=>	'something',
			'input_name'				=>	'testname',
			'company_id'				=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_field', $response['validity']);

	}

	public function test_if_adding_input_type_succeeded(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		CustomFieldType::truncate();

		$response = $this->post('/api/manage-field-types', [
			'input_type'				=>	'datetime',
			'input_name'				=>	'test datetime field',
			'company_id'				=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		/* check db */

		$field = CustomFieldType::where('input_name', '=', 'test datetime field')->first();

		$this->assertNotEmpty($field);
		
	}

	private function getQuery($token, $refresh_token, $device, $queryParams){

		$response = $this->withHeaders([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
		])->get('/api/manage-field-types?' . $queryParams);

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

		CustomFieldType::truncate();
				
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

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			CustomFieldType::factory()->create();
		}

		
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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		/* check if it is really on second page */
		$this->assertEquals('BLA2', $response['table_data']['rows'][0]['input_name']);
		$this->assertEquals(2, (int)$response['current_page']);

	}

	public function test_if_table_filters_with_per_page(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
		$this->assertEquals(5, count($response['table_data']['rows']));
		
	}

	/*
	/ combinations & tests
	
	
	/ 6. Check if column sorts, asc and desc -> change current page
	/ 10. Check if column sorts, asc and desc -> change per page -> add search term -> change current page
	/ 10. add search term -> change per page -> sort column -> goto current page 2 -> should show current page 2
	/ 11. test for malicious column label
	*/

	public function test_if_page_shows_page_1_for_current_page_search_term(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);
		

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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);
		

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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);
		

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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);
		

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

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

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

		$response = $this->getQuery($token, $refresh_token, $device, $queryParams);
		

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

}
