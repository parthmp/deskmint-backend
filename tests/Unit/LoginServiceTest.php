<?php

namespace Tests\Unit;

use App\Mail\SendOTP;
use App\Models\AccessTokenData;
use App\Models\RefreshToken;
use App\Models\TwoFactorAuthToken;
use App\Models\User;
use App\Services\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class LoginServiceTest extends TestCase{

	use RefreshDatabase;

    public function test_check_login_auth_with_no_user(): void{

		$instance = app(LoginService::class);
        $this->assertFalse($instance->CheckLoginAuth('foo@bar.com', '12346789'));

    }

	public function test_check_login_auth_with_correct_credentials(): void{

		User::factory()->create([
			'email'			=>		'foo@bar.com',
			'password'		=>		Hash::make('1234567890TEST')
		]);

		$instance = app(LoginService::class);
        $this->assertTrue($instance->CheckLoginAuth('foo@bar.com', '1234567890TEST'));
		
    }

	public function test_send_otp_email_dispatches_mail(): void{
    
		Mail::fake();

		$user = User::factory()->create();
		$otp = 123456;

		app(LoginService::class)->sendOTPEmail($user, $otp);

		Mail::assertQueued(SendOTP::class, function ($mail) use ($user, $otp) {
			return $mail->hasTo($user->email) && $mail->getOtp() === $otp;
		});

	}

	public function test_generate_otp_and_token_method() : void{

		Mail::fake();

		$user = User::factory()->create();
    	$device = 'device-id-123';

		$result = app(LoginService::class)->generateOtpAndToken($user, $device);

		$this->assertArrayHasKey('token', $result);
    	$this->assertIsString($result['token']);

		$this->assertDatabaseHas('2fa_tokens', [
			'user_id' 	=> $user->id,
			'token' 	=> $result['token'],
			'device' 	=> $device,
		]);

		 Mail::assertQueued(SendOTP::class, function ($mail) use ($user) {
        	return $mail->hasTo($user->email);
    	});

	}

	public function test_if_token_is_valid_without_database_entry() : void{
		$result = app(LoginService::class)->isTokenValid(null, '');
		$this->assertFalse($result);
	}

	public function test_if_token_is_valid_with_valid_token() : void{

		Config::set('global.otp_expiry', 300);

		$token = TwoFactorAuthToken::factory()->create([
			'created_at' => now()->subSeconds(299)
		]);

		$this->assertTrue(app(LoginService::class)->isTokenValid($token, $token->device));
		
	}

	public function test_token_is_invalid_after_expiry_time(): void{

		Config::set('global.otp_expiry', 300);

		$token = TwoFactorAuthToken::factory()->create([
			'created_at' => now()->subSeconds(301)
		]);

		$this->assertFalse(app(LoginService::class)->isTokenValid($token, $token->device));

	}

	public function test_invalidate_past_tokens_if_not_added() : void{

		$user = User::factory()->create();
		$device = 'device-123';

		app(LoginService::class)->invalidatePastTokens($user, $device);

		$access_token_rows = AccessTokenData::where([['user_id', '=', $user->id], ['device', '=', $device]])->get();
		$refresh_token_rows = RefreshToken::where([['user_id', '=', $user->id], ['device', '=', $device]])->get();

		$this->assertInstanceOf(\Illuminate\Support\Collection::class, $access_token_rows);
		$this->assertTrue($access_token_rows->isEmpty());
		$this->assertCount(0, $access_token_rows);

		$this->assertInstanceOf(\Illuminate\Support\Collection::class, $refresh_token_rows);
		$this->assertTrue($refresh_token_rows->isEmpty());
		$this->assertCount(0, $refresh_token_rows);
		

	}

	public function test_invalidate_past_tokens_if_rows_added() : void{

		$user = User::factory()->create();
		$device = 'device-123';

		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		AccessTokenData::factory()->create([
			'token_id'	=>	$token_model->id,
			'user_id'	=>	$user->id,
			'device'	=>	$device
		]);

		RefreshToken::factory()->create([
			'user_id'	=>	$user->id,
			'device'	=>	$device
		]);

		app(LoginService::class)->invalidatePastTokens($user, $device);

		$access_token_rows = AccessTokenData::where([['user_id', '=', $user->id], ['device', '=', $device]])->get();
		$refresh_token_rows = RefreshToken::where([['user_id', '=', $user->id], ['device', '=', $device]])->get();

		$this->assertInstanceOf(\Illuminate\Support\Collection::class, $access_token_rows);
		$this->assertTrue($access_token_rows->isEmpty());
		$this->assertCount(0, $access_token_rows);

		$this->assertInstanceOf(\Illuminate\Support\Collection::class, $refresh_token_rows);
		$this->assertTrue($refresh_token_rows->isEmpty());
		$this->assertCount(0, $refresh_token_rows);
		

	}

	public function test_issue_tokens_stores_data_and_returns_tokens(): void{
        
        config(['app.name' => 'TestApp']);

       
        $user = User::factory()->create();

        
        $request = Request::create('/login', 'POST');
        $request->headers->set('User-Agent', 'TestAgent/1.0');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

		
        $result = (new LoginService)->issueTokens($user, 'android', $request);

        
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);

        $this->assertDatabaseHas('access_tokens_data', [
            'user_id' => $user->id,
            'device' => 'android',
            'user_agent' => 'TestAgent/1.0',
            'ip_address' => '127.0.0.1',
        ]);

        
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'device' => 'android',
        ]);

        
        $this->assertTrue(strlen($result['refresh_token']) === 128);
    }

}
