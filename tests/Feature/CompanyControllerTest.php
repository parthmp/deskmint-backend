<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Company;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CompanyControllerTest extends TestCase{

	use RefreshDatabase;

    public function test_if_company_does_not_exist(): void{
		
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
		
		$json = $response->json();

		$this->assertArrayHasKey('company_exists', $json);
		$this->assertArrayHasKey('company_id', $json);
		$this->assertFalse($json['company_exists']);
		$this->assertNull($json['company_id']);

    }

	public function test_if_company_exists(): void{
		
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

		Company::factory()->create([
			'default'	=>	1
		]);
		
		$response = $this->post('/api/check-company-exists', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertArrayHasKey('company_exists', $json);
		$this->assertArrayHasKey('company_id', $json);
		$this->assertTrue($json['company_exists']);
		$this->assertNotNull($json['company_id']);

    }

	public function test_set_default_company_with_invalid_data(): void{
		
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
		
		$response = $this->post('/api/set-default-company', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);

		$response->assertStatus((int)config('global.error_code'));
		
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

    }


	public function test_set_default_company_with_invalid_data_2(): void{
		
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
		
		$response = $this->post('/api/set-default-company', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash,
			'company_name'		=>	''
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);

		$response->assertStatus((int)config('global.error_code'));
		
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

    }


	public function test_set_default_company_success(): void{
		
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
		
		$response = $this->post('/api/set-default-company', [
			'device_id' 		=> 	$device,
			'refresh_token'		=>	$refresh_token_hash,
			'company_name'		=>	'Bla Company'
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$plain_text_token
    	]);

		$response->assertStatus(200);
		
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertArrayHasKey('company_id', $response);
		$this->assertEquals('success', $response['validity']);
		$this->assertNotNull($response['company_id']);
		$this->assertNotEmpty($response['company_id']);

    }

	
}
