<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Company;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminControllerTest extends TestCase{

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
	

    public function test_if_anauthorized_user_fails_to_fetch_admins(): void{

		$device = 'device 123';

		$response = $this->post('/api/manage-admins', [
			'device_id' 		=> 	$device
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer ',
			'X-Refresh-Token' => ''
    	]);

		$response->assertStatus(401);
        
    }

	public function test_if_authorized_admin_can_fetch_admins(): void{

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
		])->get('/api/manage-admins?' . $queryParams);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertArrayHasKey('columns', $json);
		$this->assertArrayHasKey('rows', $json);

    }

	public function test_if_authorized_admin_fails_to_create_admin_with_invalid_data(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'',
			'email'				=>	'',
			'password'			=>	'',
			'confirm_password'	=>	'',
			'company_id'		=>	$company_id
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

	public function test_if_authorized_admin_fails_to_create_admin_with_invalid_data_2(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'Jack',
			'email'				=>	'jack123',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
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

	public function test_if_authorized_admin_fails_to_create_admin_with_invalid_data_3(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password1234',
			'company_id'		=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('passwords_not_matched', $response['validity']);

	}

	public function test_if_authorized_admin_fails_create_admin_email_already_exists(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'email@one.com'
		]);

		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'Jack',
			'email'				=>	'email@one.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('email_exists', $response['validity']);

	}

	public function test_if_authorized_admin_can_create_admin(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('admin_created', $response['validity']);

	}

	public function test_if_admin_fails_to_fetch_user_with_invalid_id(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$queryParams = http_build_query([
			'company_id'	=>	$company_id
		]);

		$response = $this->withHeaders([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
		])->get('/api/manage-admins/100?' . $queryParams);

		$response->assertStatus((int)config('global.error_code'));
		
		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_request', $json['validity']);

	}

	public function test_if_admin_can_fetch_user_with_valid_id(){

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
		])->get('/api/manage-admins/'.$user->id.'?' . $queryParams);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertArrayHasKey('id', $json);
		$this->assertArrayHasKey('name', $json);
		$this->assertEquals(1, (int)$json['id']);
		$this->assertEquals((int)config('global.user_types.admin'), (int)$json['user_type']);

	}


	public function test_if_authorized_admin_fails_to_update_admin_with_invalid_id(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/100', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
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


	public function test_if_authorized_admin_fails_to_update_admin_with_email_exists(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack@blackpearl.com'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack@blackpearl.com'
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('email_exists', $response['validity']);

	}

	public function test_if_authorized_admin_fails_to_update_admin_with_unmatched_passwords(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password1234',
			'company_id'		=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('passwords_not_matched', $response['validity']);

	}

	public function test_if_authorized_admin_able_to_update_an_admin_with_same_email(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack2@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('admin_updated', $response['validity']);

	}

	public function test_if_authorized_admin_able_to_update_an_admin_with_different_email(){

		$password_hash = Hash::make('password123');

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('admin_updated', $response['validity']);
		$this->assertNotEquals($user2->password, $password_hash);

	}

	public function test_if_authorized_admin_able_to_update_without_password(){

		$password_hash = Hash::make('password123');

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);

		$user2->password = $password_hash;
		$user2->save();
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'company_id'		=>	$company_id
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('admin_updated', $response['validity']);

		$this->assertEquals($user2->password, $password_hash);

	}

	public function test_if_authorized_admin_able_to_update_with_different_password(){

		$password_hash = Hash::make('password123');

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);

		$user2->password = $password_hash;
		$user2->save();
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'password'			=>	'password123DIFF',
			'confirm_password'	=>	'password123DIFF',
			'company_id'		=>	$company_id
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('admin_updated', $response['validity']);

		$fetched_user = User::where('id', '=', $user2->id)->first();

		$this->assertNotEquals($fetched_user->password, $password_hash);

	}

	public function test_if_authorized_admin_fails_to_update_with_invalid_password_fields_1(){

		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'password'			=>	'123',
			'confirm_password'	=>	'',
			'company_id'		=>	$company_id
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_authorized_admin_fails_to_update_with_invalid_password_fields_2(){

		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'password'			=>	'',
			'confirm_password'	=>	'123',
			'company_id'		=>	$company_id
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_authorized_admin_fails_to_delete_without_ids(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	'',
			'company_id'		=>	$company_id
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}

	public function test_if_authorized_admin_fails_to_delete_with_numeric_ids(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	['one'],
			'company_id'		=>	$company_id
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('non_numeric', $response['validity']);

	}

	public function test_if_authorized_admin_able_to_delete_with_ids_1(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);

		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	[$user2->id],
			'company_id'		=>	$company_id
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		
		$find_admin = User::where('id', '=', $user2->id)->first();
		$this->assertNull($find_admin);

	}

	public function test_if_authorized_admin_able_to_delete_with_ids_2(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);

		$user3 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack3@blackpearl.com'
		]);

		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	[$user2->id, $user3->id],
			'company_id'		=>	$company_id
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus(200);
		
		$find_admin = User::where('id', '=', $user2->id)->first();
		$this->assertNull($find_admin);

		$find_admin2 = User::where('id', '=', $user3->id)->first();
		$this->assertNull($find_admin2);

	}

	public function test_if_authorized_admin_fails_if_refresh_token_and_device_posted_in_para(){

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack1@blackpearl.com',
			'password'		=>		'password123'
		]);

		$user2 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack2@blackpearl.com'
		]);

		$user3 = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin'),
			'email'			=>		'jack3@blackpearl.com'
		]);

		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	[$user2->id, $user3->id],
			'company_id'		=>	$company_id,
			'device_id' 		=> 	$device,
			'refresh_token' 	=> 	$refresh_token
		], [
        	'Accept' 		=> 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	
}
