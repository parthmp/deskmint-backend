<?php

namespace Tests\Feature;

use App\Helpers\Turnstile;
use App\Mail\SendLoginEmail;
use App\Models\AccessTokenData;
use App\Models\LoginAttempt;
use App\Models\RefreshToken;
use App\Models\Setting;
use App\Models\TwoFactorAuthToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
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

	public function test_resend_otp_invalid_request_1(): void{

		$response = $this->post('/api/resend-otp', [
			'token' 			=> '',
			'device' 			=> ''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_resend_otp_invalid_request_2(): void{

		$response = $this->post('/api/resend-otp', [
			'token' 			=> '123456789',
			'device' 			=> ''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_resend_otp_invalid_request_3(): void{

		$response = $this->post('/api/resend-otp', [
			'token' 			=> '',
			'device' 			=> 'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_resend_otp_user_needs_to_login_again(): void{

		$token = hash('sha512', uniqid());
		$device = 'device 123';

		Config::set('global.otp_expiry', 300);

		TwoFactorAuthToken::truncate();

		TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(15)
		]);

		$response = $this->post('/api/resend-otp', [
			'token' 			=> $token,
			'device' 			=> $device
		]);

		$response->assertStatus(500);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('login_again', $response['validity']);

	}

	public function test_resend_otp_sucess(): void{

		$token = hash('sha512', uniqid());
		$device = 'device 123';

		Config::set('global.otp_expiry', 300);

		TwoFactorAuthToken::truncate();

		TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(2)
		]);

		$response = $this->post('/api/resend-otp', [
			'token' 			=> $token,
			'device' 			=> $device
		]);

		$response->assertStatus(200);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('otp_resent', $response['validity']);

	}

	public function test_if_otp_is_valid_invalid_data_1(): void{

		

		$response = $this->post('/api/validate-otp', [
			'token' 			=> 	'',
			'otp'				=>	'',
			'device' 			=> 	''
		]);

		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_otp_is_valid_invalid_data_2(): void{

		
		$response = $this->post('/api/validate-otp', [
			'token' 			=> 	'',
			'otp'				=>	'123456',
			'device' 			=> 	''
		]);

		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_otp_is_valid_invalid_data_3(): void{

		$response = $this->post('/api/validate-otp', [
			'token' 			=> 	'21123',
			'otp'				=>	'123456',
			'device' 			=> 	''
		]);

		$response->assertStatus(401);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}


	public function test_if_otp_is_valid_expired_otp(): void{

		$token = hash('sha512', uniqid());
		$device = 'device 123';
		$otp = 123456;

		Config::set('global.otp_expiry', 600);

		TwoFactorAuthToken::truncate();

		TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'otp'			=>		$otp,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(15)
		]);

		$response = $this->post('/api/validate-otp', [
			'token' 			=> 	$token,
			'otp'				=>	$otp,
			'device' 			=> 	$device
		]);

		$response->assertStatus(500);
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('token_expired', $response['validity']);

	}


	public function test_if_otp_is_valid_with_valid_tokens_and_past_tokens_are_invalidated(): void{

		$token = hash('sha512', uniqid());
		$device = 'device 123';
		$otp = 123456;

		Config::set('global.otp_expiry', 600);

		TwoFactorAuthToken::truncate();

		$past_token = TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'otp'			=>		$otp,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(2)
		]);

		$current_token = TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'otp'			=>		$otp,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(1)
		]);

		Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 15,
			'login_limits_attempts' => 3,
			'login_email_flag'		=>	0
		]);

		$response = $this->post('/api/validate-otp', [
			'token' 			=> 	$token,
			'otp'				=>	$otp,
			'device' 			=> 	$device
		]);

		$response->assertStatus(200);
		
		$this->assertArrayHasKey('token', $response);
		$this->assertArrayHasKey('refresh_token', $response);
		
		$this->assertTrue(strlen($response['refresh_token']) === 128);

		$current_token_temp = TwoFactorAuthToken::where('id', '=', $current_token->id)->first();

		$this->assertEquals(1, $current_token_temp->used);

		$past_refresh_tokens = RefreshToken::where([['user_id', '=', $current_token_temp->user->id], ['device', '=', $device], ['refresh_token', '!=', $response['refresh_token']]])->get();
		$this->assertEmpty($past_refresh_tokens);

		$token = PersonalAccessToken::findToken($response['token']);

		$past_access_token_data = AccessTokenData::where([['user_id', '=', $current_token_temp->user->id], ['device', '=', $device], ['token_id', '!=', $token->id]])->get();
		$this->assertEmpty($past_access_token_data);

	}

	public function test_if_it_does_not_send_login_email_with_setting_off_2fa(): void{

		Mail::fake();

		$token = hash('sha512', uniqid());
		$device = 'device 123';
		$otp = 123456;

		Config::set('global.otp_expiry', 600);

		TwoFactorAuthToken::truncate();

		$past_token = TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'otp'			=>		$otp,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(2)
		]);

		$current_token = TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'otp'			=>		$otp,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(1)
		]);

		Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 15,
			'login_limits_attempts' => 3,
			'two_factor_auth_flag'	=>	1,
			'login_email_flag'		=>	0
		]);

		$response = $this->post('/api/validate-otp', [
			'token' 			=> 	$token,
			'otp'				=>	$otp,
			'device' 			=> 	$device
		]);

		$response->assertStatus(200);
		
		$this->assertArrayHasKey('token', $response);
		$this->assertArrayHasKey('refresh_token', $response);
		
		$this->assertTrue(strlen($response['refresh_token']) === 128);

		$current_token_temp = TwoFactorAuthToken::where('id', '=', $current_token->id)->first();

		$this->assertEquals(1, $current_token_temp->used);

		$past_refresh_tokens = RefreshToken::where([['user_id', '=', $current_token_temp->user->id], ['device', '=', $device], ['refresh_token', '!=', $response['refresh_token']]])->get();
		$this->assertEmpty($past_refresh_tokens);

		$token = PersonalAccessToken::findToken($response['token']);

		$past_access_token_data = AccessTokenData::where([['user_id', '=', $current_token_temp->user->id], ['device', '=', $device], ['token_id', '!=', $token->id]])->get();
		$this->assertEmpty($past_access_token_data);

		/* now test if it did not send an email */
		Mail::assertNotQueued(SendLoginEmail::class, function ($mail) use ($current_token_temp) {
			return $mail->hasTo($current_token_temp->user->email);
		});

	}

	public function test_if_it_sends_login_email_with_setting_on_2fa(): void{

		Mail::fake();

		$token = hash('sha512', uniqid());
		$device = 'device 123';
		$otp = 123456;

		Config::set('global.otp_expiry', 600);

		TwoFactorAuthToken::truncate();

		$past_token = TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'otp'			=>		$otp,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(2)
		]);

		$current_token = TwoFactorAuthToken::factory()->create([
			'token'			=>		$token,
			'device'		=>		$device,
			'otp'			=>		$otp,
			'used'			=>		0,
			'created_at'	=>		now()->subMinutes(1)
		]);

		Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 15,
			'login_limits_attempts' => 3,
			'two_factor_auth_flag'	=>	1,
			'login_email_flag'		=>	1
		]);

		$response = $this->post('/api/validate-otp', [
			'token' 			=> 	$token,
			'otp'				=>	$otp,
			'device' 			=> 	$device
		]);

		$response->assertStatus(200);
		
		$this->assertArrayHasKey('token', $response);
		$this->assertArrayHasKey('refresh_token', $response);
		
		$this->assertTrue(strlen($response['refresh_token']) === 128);

		$current_token_temp = TwoFactorAuthToken::where('id', '=', $current_token->id)->first();

		$this->assertEquals(1, $current_token_temp->used);

		$past_refresh_tokens = RefreshToken::where([['user_id', '=', $current_token_temp->user->id], ['device', '=', $device], ['refresh_token', '!=', $response['refresh_token']]])->get();
		$this->assertEmpty($past_refresh_tokens);

		$token = PersonalAccessToken::findToken($response['token']);

		$past_access_token_data = AccessTokenData::where([['user_id', '=', $current_token_temp->user->id], ['device', '=', $device], ['token_id', '!=', $token->id]])->get();
		$this->assertEmpty($past_access_token_data);

		/* now test if it did send an email */
		Mail::assertQueued(SendLoginEmail::class, function ($mail) use ($current_token_temp) {
			return $mail->hasTo($current_token_temp->user->email);
		});

	}

	public function test_if_it_does_not_send_login_email_with_setting_off_no_2fa(): void{

		Mail::fake();

		
		$device = 'device 123';
	
		Config::set('global.otp_expiry', 600);

		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);

		Setting::factory()->create([
			'login_limits_flag'     => 	1,
			'login_limits_minutes'  => 	15,
			'login_limits_attempts' => 	3,
			'two_factor_auth_flag'	=>	0,
			'login_email_flag'		=>	0
		]);

		$user = User::factory()->create([
			'email'		=>	'one@test.com',
			'password'	=>	Hash::make('password123')
		]);

		$response = $this->post('/api/login', [
			'email_address' 		=> 'one@test.com',
			'password' 				=> 'password123',
			'turnstile_token'		=> 'valid_turnstile_token',
			'device' 				=> $device
		]);

		$response->assertStatus(200);
		
		$this->assertArrayHasKey('token', $response);
		$this->assertArrayHasKey('refresh_token', $response);
		
		$this->assertTrue(strlen($response['refresh_token']) === 128);

		/* now test if it did not send an email */
		Mail::assertNotQueued(SendLoginEmail::class, function ($mail) use ($user) {
			return $mail->hasTo($user->email);
		});

	}

	public function test_if_it_did_send_email_with_setting_off_no_2fa(): void{

		Mail::fake();

		
		$device = 'device 123';
	
		Config::set('global.otp_expiry', 600);

		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);

		Setting::factory()->create([
			'login_limits_flag'     => 	1,
			'login_limits_minutes'  => 	15,
			'login_limits_attempts' => 	3,
			'two_factor_auth_flag'	=>	0,
			'login_email_flag'		=>	1
		]);

		$user = User::factory()->create([
			'email'		=>	'one@test.com',
			'password'	=>	Hash::make('password123')
		]);

		$response = $this->post('/api/login', [
			'email_address' 		=> 'one@test.com',
			'password' 				=> 'password123',
			'turnstile_token'		=> 'valid_turnstile_token',
			'device' 				=> $device
		]);

		$response->assertStatus(200);
		
		$this->assertArrayHasKey('token', $response);
		$this->assertArrayHasKey('refresh_token', $response);
		
		$this->assertTrue(strlen($response['refresh_token']) === 128);

		/* now test if it did send an email */
		Mail::assertQueued(SendLoginEmail::class, function ($mail) use ($user) {
			return $mail->hasTo($user->email);
		});

	}
	
}
