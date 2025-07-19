<?php

namespace Tests\Feature;

use App\Helpers\Turnstile;
use App\Models\LoginAttempt;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    
	use RefreshDatabase;

    public function test_if_invalid_fields_1(): void {
        
		$response = $this->post('/api/login', [
			'email_address' 	=> 'foobar.com',
			'password' 			=> '1234678',
			'turnstile_token'	=>	'123467980',
			'device'			=>	'device 123'
		]);
		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_fields', $response['validity']);

    }

	public function test_if_invalid_fields_2(): void {
        
		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '',
			'turnstile_token'	=>	'123467980',
			'device'			=>	'device 123'
		]);
		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_fields', $response['validity']);

    }

	public function test_if_invalid_fields_3(): void {
        
		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '123456789',
			'turnstile_token'	=>	'',
			'device'			=>	'device 123'
		]);
		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_fields', $response['validity']);

    }

	public function test_if_invalid_fields_4(): void {
        
		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '123456789',
			'turnstile_token'	=>	'123456789',
			'device'			=>	''
		]);
		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_fields', $response['validity']);

    }

	public function test_if_turnstile_invalid(): void {
        
		$response = $this->post('/api/login', [
			'email_address' 	=> 	'foo@bar.com',
			'password' 			=> 	'123456789',
			'turnstile_token'	=>	'123456789',
			'device'			=>	'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_turnstile', $response['validity']);

    }

	public function test_if_turnstile_is_valid_and_user_does_not_exist(): void {
        
		
		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);

		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '123456789',
			'turnstile_token'	=> 'valid_turnstile_token',
			'device'			=> 'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_email_and_password', $response['validity']);


    }

	public function test_if_user_locked_out(): void {
        
		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);

		$user = User::factory()->create([
			'email'		=>	'foo@bar.com',
			'password'	=> Hash::make('123456789')
		]);
		Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 15,
			'login_limits_attempts' => 3
		]);

		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 3,
			'last_attempted_at'     => now()->subMinutes(5)
		]);

		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '123456789',
			'turnstile_token'	=> 'valid_turnstile_token',
			'device'			=> 'device 123'
		]);
		

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('locked_out', $response['validity']);



    }


	public function test_if_user_is_locked_out_for_specific_time(): void {
        
		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);
		
		$user = User::factory()->create([
			'email'		=>	'foo@bar.com',
			'password'	=> 	Hash::make('123456789_temp')
		]);
		Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 15,
			'login_limits_attempts' => 3
		]);

		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 2,
			'last_attempted_at'     => now()->subMinutes(5)
		]);

		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '123456789',
			'turnstile_token'	=> 'valid_turnstile_token',
			'device'			=> 'device 123'
		]);
		

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('locked_out_for_time', $response['validity']);



    }

	public function test_if_user_is_invalid_with_login_limits_set_to_false(): void {
        
		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);
		
		$user = User::factory()->create([
			'email'		=>	'foo@bar.com',
			'password'	=> 	Hash::make('123456789_temp')
		]);
		Setting::factory()->create([
			'login_limits_flag'     => 0,
			'login_limits_minutes'  => 15,
			'login_limits_attempts' => 3
		]);

		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 2,
			'last_attempted_at'     => now()->subMinutes(5)
		]);

		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '123456789',
			'turnstile_token'	=> 'valid_turnstile_token',
			'device'			=> 'device 123'
		]);
		

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_email_password', $response['validity']);



    }

	public function test_if_user_is_valid_without_two_factor_auth(): void {
        
		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);
		
		$user = User::factory()->create([
			'email'		=>	'foo@bar.com',
			'password'	=> 	Hash::make('123456789')
		]);
		Setting::factory()->create([
			'login_limits_flag'     => 	1,
			'login_limits_minutes'  => 	15,
			'login_limits_attempts' => 	3,
			'two_factor_auth_flag'	=>	0
		]);

		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 2,
			'last_attempted_at'     => now()->subMinutes(5)
		]);

		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '123456789',
			'turnstile_token'	=> 'valid_turnstile_token',
			'device'			=> 'device 123'
		]);
		

		
		$response->assertStatus(200);
		$this->assertArrayHasKey('token', $response);
		$this->assertArrayHasKey('refresh_token', $response);

		$this->assertTrue(strlen($response['refresh_token']) === 128);

    }

	public function test_if_user_is_valid_with_two_factor_auth_tfa_token_issued(): void {
        
		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);
		
		$user = User::factory()->create([
			'email'		=>	'foo@bar.com',
			'password'	=> 	Hash::make('123456789')
		]);
		Setting::factory()->create([
			'login_limits_flag'     => 	1,
			'login_limits_minutes'  => 	15,
			'login_limits_attempts' => 	3,
			'two_factor_auth_flag'	=>	1
		]);

		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 2,
			'last_attempted_at'     => now()->subMinutes(5)
		]);

		$response = $this->post('/api/login', [
			'email_address' 	=> 'foo@bar.com',
			'password' 			=> '123456789',
			'turnstile_token'	=> 'valid_turnstile_token',
			'device'			=> 'device 123'
		]);
		

		
		$response->assertStatus(200);
		$this->assertArrayHasKey('token', $response);
		$this->assertArrayHasKey('validity', $response);
		$this->assertArrayHasKey('tfa', $response);

		$this->assertEquals('otp_sent', $response['validity']);
		$this->assertEquals(true, $response['tfa']);

		$this->assertTrue(strlen($response['token']) === 128);

    }

	

	
}
