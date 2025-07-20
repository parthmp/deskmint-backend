<?php

namespace Tests\Feature;

use App\Mail\SendResetPasswordEmail;
use App\Models\AccessTokenData;
use App\Models\CustomPasswordReset;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordControllerTest extends TestCase{

	use RefreshDatabase;

    public function test_send_reset_password_code_invalid_data_1(): void{

        $response = $this->post('/api/send-reset-password-code', [
			'email_address' 	=> 	'',
			'turnstile_token'	=>	'',
			'device'			=>	''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

    }

	public function test_send_reset_password_code_invalid_data_2(): void{

        $response = $this->post('/api/send-reset-password-code', [
			'email_address' 	=> 	'foo@bar.com',
			'turnstile_token'	=>	'',
			'device'			=>	''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

    }

	public function test_send_reset_password_code_invalid_data_3(): void{

        $response = $this->post('/api/send-reset-password-code', [
			'email_address' 	=> 	'foo@bar.com',
			'turnstile_token'	=>	'test123',
			'device'			=>	''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

    }

	public function test_send_reset_password_code_invalid_data_4(): void{

        $response = $this->post('/api/send-reset-password-code', [
			'email_address' 	=> 	'foobar.com',
			'turnstile_token'	=>	'test123',
			'device'			=>	'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

    }

	public function test_send_reset_password_code_invalid_turnstile(): void{

        $response = $this->post('/api/send-reset-password-code', [
			'email_address' 	=> 	'foo@bar.com',
			'turnstile_token'	=>	'test123',
			'device'			=>	'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_turnstile', $response['validity']);

    }

	public function test_send_reset_password_code_valid_turnstile_but_no_user_found(): void{

		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);

        $response = $this->post('/api/send-reset-password-code', [
			'email_address' 	=> 	'foo@bar.com',
			'turnstile_token'	=> 'valid_turnstile_token',
			'device'			=>	'device 123'
		]);

		
		$response->assertStatus(200);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('sent_reset_code', $response['validity']);

    }

	public function test_send_reset_password_code_valid_turnstile_with_user_found(): void{

		Mail::fake();

		\Illuminate\Support\Facades\Http::fake([
			'https://challenges.cloudflare.com/turnstile/v0/siteverify' => \Illuminate\Support\Facades\Http::response([
				'success' => true
			], 200)
		]);

		$user = User::factory()->create([
			'email'	=>	'foo@bar.com'
		]);


        $response = $this->post('/api/send-reset-password-code', [
			'email_address' 	=> 	'foo@bar.com',
			'turnstile_token'	=> 'valid_turnstile_token',
			'device'			=>	'device 123'
		]);

		
		$response->assertStatus(200);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('sent_reset_code', $response['validity']);

		/** test if token generated */
		$custom_pass_reset = CustomPasswordReset::where([['user_id', '=', $user->id], ['device', '=', 'device 123']])->first();
		$this->assertInstanceOf(CustomPasswordReset::class, $custom_pass_reset);
		$this->assertNotNull($custom_pass_reset);
		
		/* now test if it sent an email */
		Mail::assertQueued(SendResetPasswordEmail::class, function ($mail) use ($user, $custom_pass_reset) {
			return $mail->hasTo($user->email) && $mail->getResetCode() === $custom_pass_reset->reset_code;
		});

		

    }

	public function test_reset_password_invalid_data_1(): void{

		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'',
			'password'				=>	'',
			'retype_password'		=>	'',
			'device'				=>	''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

    }

	public function test_reset_password_invalid_data_2(): void{

		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'test123',
			'password'				=>	'',
			'retype_password'		=>	'',
			'device'				=>	''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

    }

	public function test_reset_password_invalid_data_3(): void{

		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'test123',
			'password'				=>	'123456pass',
			'retype_password'		=>	'',
			'device'				=>	''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

    }

	public function test_reset_password_invalid_data_4(): void{

		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'test123',
			'password'				=>	'123456pass',
			'retype_password'		=>	'123456pass',
			'device'				=>	''
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

    }

	public function test_reset_password_not_matching_passwords(): void{

		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'test123',
			'password'				=>	'123456pass',
			'retype_password'		=>	'123456pass1',
			'device'				=>	'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('passwords_do_not_match', $response['validity']);

    }

	public function test_reset_password_invalid_reset_code(): void{

		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'RESETCODE123',
			'password'				=>	'123456pass',
			'retype_password'		=>	'123456pass',
			'device'				=>	'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_reset_code', $response['validity']);

    }

	public function test_reset_password_expired_reset_code_1(): void{

		Config::set('global.reset_code_expiry', 300);

		$user = User::factory()->create();

		CustomPasswordReset::factory()->create([
			'user_id'		=>		$user->id,
			'reset_code'	=>		'RESETCODE123',
			'device'		=>		'device 123',
			'used'			=>		0,
			'used_at'		=>		null,
			'created_at'	=>		now()->subSeconds(301)
		]);


		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'RESETCODE123',
			'password'				=>	'123456pass',
			'retype_password'		=>	'123456pass',
			'device'				=>	'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('reset_code_expired', $response['validity']);

    }

	public function test_reset_password_expired_reset_code_2(): void{

		Config::set('global.reset_code_expiry', 300);

		$user = User::factory()->create();

		CustomPasswordReset::factory()->create([
			'user_id'		=>		$user->id,
			'reset_code'	=>		'RESETCODE123',
			'device'		=>		'device 123',
			'used'			=>		0,
			'used_at'		=>		null,
			'created_at'	=>		now()->subSeconds(600)
		]);


		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'RESETCODE123',
			'password'				=>	'123456pass',
			'retype_password'		=>	'123456pass',
			'device'				=>	'device 123'
		]);

		$expected = (int)config('global.error_code');
		$response->assertStatus($expected);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('reset_code_expired', $response['validity']);

    }


	public function test_reset_password_success(): void{

		Config::set('global.reset_code_expiry', 300);

		$user = User::factory()->create([
			'password'	=>	Hash::make('test123')
		]);

		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		AccessTokenData::factory()->create([
			'token_id'		=>		$token_model->id,
			'user_id'		=>		$user->id,
			'device'		=>		'device 123'
		]);

		RefreshToken::factory()->create([
			'user_id'		=>		$user->id,
			'device'		=>		'device 123'
		]);

		CustomPasswordReset::factory()->create([
			'user_id'		=>		$user->id,
			'created_at'	=>		now()->subSeconds(500)
		]);

		CustomPasswordReset::factory()->create([
			'user_id'		=>		$user->id,
			'created_at'	=>		now()->subSeconds(501)
		]);

		CustomPasswordReset::factory()->create([
			'user_id'		=>		$user->id,
			'created_at'	=>		now()->subSeconds(502)
		]);

		$pass_reset_past = CustomPasswordReset::factory()->create([
			'user_id'		=>		$user->id,
			'reset_code'	=>		'RESETCODE123',
			'device'		=>		'device 123',
			'used'			=>		0,
			'used_at'		=>		null,
			'created_at'	=>		now()->subSeconds(rand(1,299))
		]);


		$response = $this->post('/api/reset-password', [
			'reset_code' 			=> 	'RESETCODE123',
			'password'				=>	'123456pass',
			'retype_password'		=>	'123456pass',
			'device'				=>	'device 123'
		]);

		$response->assertStatus(200);

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('password_changed', $response['validity']);


		/* check if password changed */
		$user = User::where('id', '=', $user->id)->first();
		$this->assertTrue(Hash::check('123456pass', $user->password));
		
		/* check if sessions are invalidated for the same device */
		$pass_reset = CustomPasswordReset::where('id', '=', $pass_reset_past->id)->withTrashed()->first();
		$this->assertEquals(1, $pass_reset->used);
		$this->assertNotNull($pass_reset->used_at);

		$pass_resets = CustomPasswordReset::where('id', '=', $pass_reset_past->id)->get();
		$this->isEmpty($pass_resets);

		$this->isEmpty(AccessTokenData::where([['user_id', '=', $user->id], ['device', '=', 'device 123']])->get());
		$this->isEmpty(RefreshToken::where([['user_id', '=', $user->id], ['device', '=', 'device 123']])->get());


    }




}
