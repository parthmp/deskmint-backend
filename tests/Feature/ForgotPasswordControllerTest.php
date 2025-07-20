<?php

namespace Tests\Feature;

use App\Mail\SendResetPasswordEmail;
use App\Models\CustomPasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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



}
