<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ValidateDeviceAndTokensTest extends TestCase{

	use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_if_auth_fails_with_no_data_check_if_company_exists_route_1(): void{
        
		$response = $this->post('/api/check-company-exists', [
			'bla' => '123'
		], [
        	'Accept' => 'application/json'
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('message', $response);
		$this->assertEquals('Unauthenticated.', $response['message']);

    }

	public function test_if_auth_fails_with_no_data_and_only_sanctum_token_check_if_company_exists_route_2(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		/*$token_model = $access_token->accessToken;*/
		
		$response = $this->post('/api/check-company-exists', [
			'bla' => '123'
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$access_token->plainTextToken
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	public function test_if_auth_fails_with_no_data_and_with_token_data_check_if_company_exists_route_3(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		AccessTokenData::factory()->create([
			'token_id' 	=> $token_model->id,
			'user_id'	=> $user->id
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'bla' => '123'
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$access_token->plainTextToken
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	public function test_if_auth_fails_with_no_data_added_sanctum_token_check_if_company_exists_route_4(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		AccessTokenData::factory()->create([
			'token_id' 	=> $token_model->id,
			'user_id'	=> $user->id
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 	=> '',
			'refresh_token'	=>	''
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$access_token->plainTextToken
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	public function test_if_auth_fails_with_no_data_added_sanctum_token_check_if_company_exists_route_4_2(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		AccessTokenData::factory()->create([
			'token_id' 	=> $token_model->id,
			'user_id'	=> $user->id
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 	=> 'device 123',
			'refresh_token'	=>	''
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$access_token->plainTextToken
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	public function test_if_auth_fails_with_no_data_added_sanctum_token_check_if_company_exists_route_4_3(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		AccessTokenData::factory()->create([
			'token_id' 	=> $token_model->id,
			'user_id'	=> $user->id
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 	=> '',
			'refresh_token'	=>	'refresh 123'
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$access_token->plainTextToken
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	
	public function test_if_auth_fails_with_invalid_device_check_if_company_exists_route_5(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		$access_token = AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	'device 1234',
			'refresh_token'		=>	'refresh 123'
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }
	
}
