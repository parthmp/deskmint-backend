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
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class AdminControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

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

		$device = 'device 123';

		$company_id = $this->set_default_company();

		$ac = $this->set_access($device);
		$headers = $ac['headers'];

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders($headers)->get('/api/manage-admins?' . $queryParams);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertArrayHasKey('columns', $json);
		$this->assertArrayHasKey('rows', $json);

    }

	public function test_if_authorized_admin_fails_to_create_admin_with_invalid_data(){

		$device = 'device 123';

		$ac = $this->set_access($device);
		$headers = $ac['headers'];

		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'',
			'email'				=>	'',
			'password'			=>	'',
			'confirm_password'	=>	'',
			'company_id'		=>	$company_id
		], $headers);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_authorized_admin_fails_to_create_admin_with_invalid_data_2(){

	
		$device = 'device 123';

		$ac = $this->set_access($device);
		$headers = $ac['headers'];


		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'Jack',
			'email'				=>	'jack123',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], $headers);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	public function test_if_authorized_admin_fails_to_create_admin_with_invalid_data_3(){

		
		$device = 'device 123';

		$ac = $this->set_access($device);
		$headers = $ac['headers'];

		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password1234',
			'company_id'		=>	$company_id
		], $headers);

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

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'Jack',
			'email'				=>	'email@one.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], $this->headers($user, $device));

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('email_exists', $response['validity']);

	}

	public function test_if_authorized_admin_can_create_admin(){

		$device = 'device 123';

		
		$company_id = $this->set_default_company();
		$c =   $this->set_access($device);

		$response = $this->post('/api/manage-admins', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('admin_created', $response['validity']);

	}

	public function test_if_admin_fails_to_fetch_user_with_invalid_id(){

		
		$device = 'device 123';

		$c =  $this->set_access($device);

		$company_id = $this->set_default_company();

		$queryParams = http_build_query([
			'company_id'	=>	$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-admins/100?' . $queryParams);

		$response->assertStatus((int)config('global.error_code'));
		
		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_request', $json['validity']);

	}

	public function test_if_admin_can_fetch_user_with_valid_id(){

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$queryParams = http_build_query([
			'company_id' 	=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-admins/'.$c['user']->id.'?' . $queryParams);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertArrayHasKey('id', $json);
		$this->assertArrayHasKey('name', $json);
		$this->assertEquals(1, (int)$json['id']);
		$this->assertEquals((int)config('global.user_types.admin'), (int)$json['user_type']);

	}


	public function test_if_authorized_admin_fails_to_update_admin_with_invalid_id(){

		
		$device = 'device 123';
		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/100', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], $c['headers']);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack',
			'email'				=>	'jack@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password1234',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack2@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'password'			=>	'password123',
			'confirm_password'	=>	'password123',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'password'			=>	'password123DIFF',
			'confirm_password'	=>	'password123DIFF',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'password'			=>	'123',
			'confirm_password'	=>	'',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-admins/'.$user2->id.'', [
			'name'				=>	'Jack Updated',
			'email'				=>	'jack20@blackpearl.com',
			'password'			=>	'',
			'confirm_password'	=>	'123',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	'',
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	['one'],
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	[$user2->id],
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	[$user2->id, $user3->id],
			'company_id'		=>	$company_id
		], $headers);

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

		$headers = $this->headers($user, $device);

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-admins', [
			'ids'				=>	[$user2->id, $user3->id],
			'company_id'		=>	$company_id,
			'device_id' 		=> 	$device,
			'refresh_token' 	=> 	'abc'
		], $headers = $this->headers($user, $device));

		$response->assertStatus((int)config('global.error_code'));
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

	}

	
}
