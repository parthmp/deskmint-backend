<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\RefreshToken;
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

	
	public function test_if_auth_fails_with_no_refresh_token_check_if_company_exists_route_6(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		$access_token = AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(60)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	'refresh 123'
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	public function test_if_auth_fails_with_invalid_refresh_token_device_check_if_company_exists_route_7(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(60)
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device.' invalid',
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	(now())->subSeconds(1209600)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	public function test_if_auth_fails_with_invalid_refresh_token_check_if_company_exists_route_8(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(60)
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	(now())->subSeconds(1209600)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	public function test_if_auth_fails_with_both_tokens_invalid_check_if_company_exists_route_9(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(3600)
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	(now())->subSeconds(1209600)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);
		
		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);

    }

	public function test_auth_success_with_valid_tokens_check_if_company_exists_route_10(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

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
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);
		
		$response->assertStatus(200);
		
		
		
    }

	public function test_auth_success_with_only_valid_refresh_token_check_if_company_exists_route_10(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(5000)
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
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);
		
		$response->assertStatus(200);
		
		$this->assertArrayHasKey('access_token', $response);
		$this->assertArrayHasKey('refresh_token', $response);
		$this->assertTrue(strlen($response['refresh_token']) === 128);
		
    }

	public function test_auth_success_with_near_expiry_refresh_token_check_if_company_exists_route_11(): void{
        
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

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
			'created_at'	=>	(now())->subSeconds(1123201)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);
		
		$response->assertStatus(200);
		
		$this->assertArrayHasKey('access_token', $response);
		$this->assertArrayHasKey('refresh_token', $response);
		$this->assertTrue(strlen($response['refresh_token']) === 128);
		
    }

	public function test_auth_fails_with_used_refresh_token(): void{

		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

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
			'used'			=>	1, // Already used
			'used_at'		=>	now()->subMinutes(10),
			'created_at'	=>	now()->subSeconds(100)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
		]);
		
		$response->assertStatus(401);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);
	}

	/**
	 * Test boundary condition - access token exactly at expiry (3600 seconds)
	 */
	public function test_auth_fails_with_access_token_exactly_at_expiry(): void
	{
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(3600) // Exactly 1 hour
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	now()->subSeconds(100)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
		]);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('access_token', $response);
		$this->assertArrayHasKey('refresh_token', $response);
	}

	/**
	 * Test boundary condition - refresh token exactly at expiry (14 days)
	 */
	public function test_auth_fails_with_refresh_token_exactly_at_expiry(): void
	{
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

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
			'created_at'	=>	now()->subSeconds(1209600) // Exactly 14 days
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
		]);
		
		$response->assertStatus(401);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);
	}

	/**
	 * Test that old tokens are actually invalidated after new ones are issued
	 */
	public function test_old_tokens_are_invalidated_after_refresh(): void
	{
		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(5000) // Expired access token
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		$old_refresh_token = RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	now()->subSeconds(100)
		]);
		
		// First request should issue new tokens
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
		]);
		
		$response->assertStatus(200);
		
		// Verify old refresh token is marked as used
		$refreshed_token_data = RefreshToken::where('id', '=', $old_refresh_token->id)->withTrashed()->first();

		$this->assertEquals(1, $refreshed_token_data->used);
		
		// Verify old access token data is deleted
		$this->assertNotNull($refreshed_token_data->deleted_at);
	}

	public function test_user_isolation_different_users_same_device(): void{

		$userA = User::factory()->create();
		$userB = User::factory()->create();
		
		$access_token_a = $userA->createToken(env("APP_NAME"));
		$token_model_a = $access_token_a->accessToken;
		$plain_text_token_a = $access_token_a->plainTextToken;

		$device = 'shared_device_123';

		// Create tokens for User A
		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model_a->id,
			'user_id'		=> 	$userA->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(3599)
		]);

		// Create refresh token for User B on same device
		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$userB->id, // Different user
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	now()->subSeconds(100)
		]);
		
		// User A tries to use User B's refresh token
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token_a
		]);
		
		$response->assertStatus(401);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);
	}

	public function test_handles_malicious_input_safely(): void{

		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$malicious_device = "<script>alert('xss')</script>";
		$malicious_refresh = "'; DROP TABLE users; --";

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	'normal_device',
			'created_at'	=>	now()->subSeconds(3599)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$malicious_device,
			'refresh_token'		=>	$malicious_refresh
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
		]);
		
		// Should fail authentication due to device mismatch, not crash
		$response->assertStatus(401);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('unauthorized', $response['validity']);
	}

	public function test_user_agent_and_ip_are_stored_correctly(): void{

		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';
		$test_user_agent = 'Mozilla/5.0 Test Browser';
		$test_ip = '192.168.1.100';

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(5000) // Expired
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	now()->subSeconds(100)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token,
			'User-Agent' => $test_user_agent,
			'REMOTE_ADDR' => $test_ip
		]);
		
		$response->assertStatus(200);
		
		// Verify new access token data includes User-Agent and IP
		$this->assertDatabaseHas('access_tokens_data', [
			'user_id' => $user->id,
			'device' => $device,
			'user_agent' => $test_user_agent,
			'ip_address' => $test_ip
		]);
	}

	public function test_refresh_token_exactly_at_near_expiry_boundary(): void{

		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

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
			'created_at'	=>	now()->subSeconds(1123199)
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
		]);
		
		$response->assertStatus(200);
		
		$this->assertArrayNotHasKey('access_token', $response);
		$this->assertArrayNotHasKey('refresh_token', $response);

	}

	public function test_multiple_refresh_tokens_uses_latest_unused(): void{

		$user = User::factory()->create();
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		$device = 'device 123';

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(3601)
		]);

		// Create older refresh token
		$old_refresh_token_plain_text = bin2hex(random_bytes(32));
		$old_refresh_token_hash = hash('sha512', $old_refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$old_refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	now()->subSeconds(200)
		]);

		// Create newer refresh token
		$new_refresh_token_plain_text = bin2hex(random_bytes(32));
		$new_refresh_token_hash = hash('sha512', $new_refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$new_refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	now()->subSeconds(100)
		]);
		
		// Try to use the newer refresh token
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$new_refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
		]);
		
		$response->assertStatus(200);
		
		// Try to use the older refresh token (should fail as it should be deleted)
		$response2 = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$old_refresh_token_hash
		], [
			'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
		]);
		
		$response2->assertStatus(401);
	}

	
}
