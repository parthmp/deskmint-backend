<?php

namespace Tests\Unit;

use Tests\TestCase;

use App\Helpers\LoginHelper;
use App\Models\LoginAttempt;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;


class LoginHelperTest extends TestCase
{
	
	use RefreshDatabase;

	public function test_if_user_locked_out_edge_case_exactly_at_limit(): void{
		$user = User::factory()->create();
		
		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 3,
			'last_attempted_at'     => now()->subMinutes(10)
		]);
		$setting = Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 10,
			'login_limits_attempts' => 3
		]);

		$this->assertFalse(LoginHelper::ifUserIsLockedOut($user, $setting));
	}

	public function test_if_user_locked_out_edge_case_one_second_before_unlock(): void{

		$user = User::factory()->create();
		
		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 3,
			'last_attempted_at'     => now()->subSeconds(599)
		]);
		$setting = Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 10,
			'login_limits_attempts' => 3
		]);

		$this->assertTrue(LoginHelper::ifUserIsLockedOut($user, $setting));
	}

	public function test_if_user_locked_out_zero_attempts_limit(): void{
		$user = User::factory()->create();
		
		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 1,
			'last_attempted_at'     => now()->subMinutes(5)
		]);
		$setting = Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 10,
			'login_limits_attempts' => 0
		]);

		$this->assertTrue(LoginHelper::ifUserIsLockedOut($user, $setting));
	}

	public function test_if_user_locked_out_zero_minutes_limit(): void{
		$user = User::factory()->create();
		
		LoginAttempt::factory()->create([
			'user_id'               => $user->id,
			'number_of_attempts'    => 3,
			'last_attempted_at'     => now() // just happened
		]);
		$setting = Setting::factory()->create([
			'login_limits_flag'     => 1,
			'login_limits_minutes'  => 0, // edge case: 0 minutes lockout
			'login_limits_attempts' => 3
		]);

		$this->assertFalse(LoginHelper::ifUserIsLockedOut($user, $setting));
	}

    public function test_if_user_locked_out_with_no_login_limits_flag(): void
    {
        $user = User::factory()->create();
		$setting = Setting::factory()->create([
			'login_limits_flag'	=>	0
		]);

		$this->assertFalse(LoginHelper::ifUserIsLockedOut($user, $setting));

    }

	public function test_if_user_locked_out_with_login_limits_flag(): void
    {
        $user = User::factory()->create();
		$setting = Setting::factory()->create([
			'login_limits_flag'	=>	1
		]);

		$this->assertFalse(LoginHelper::ifUserIsLockedOut($user, $setting));

    }

	public function test_if_user_locked_out_with_equal_allowed_attempts(): void
    {
        $user = User::factory()->create();
		
		LoginAttempt::factory()->create([
			'user_id'				=>	$user->id,
			'number_of_attempts' 	=> 	3,
			'last_attempted_at'		=>	now()->subMinutes(5)
		]);
		$setting = Setting::factory()->create([
			'login_limits_flag'		=>	1,
			'login_limits_minutes'	=>	10,
			'login_limits_attempts'	=>	3
		]);

		$this->assertTrue(LoginHelper::ifUserIsLockedOut($user, $setting));

    }

	public function test_if_user_locked_out_with_lower_attempts(): void
    {
        $user = User::factory()->create();
		
		LoginAttempt::factory()->create([
			'user_id'				=>	$user->id,
			'number_of_attempts' 	=> 	2,
			'last_attempted_at'		=>	now()->subMinutes(5)
		]);
		$setting = Setting::factory()->create([
			'login_limits_flag'		=>	1,
			'login_limits_minutes'	=>	10,
			'login_limits_attempts'	=>	3
		]);

		$this->assertFalse(LoginHelper::ifUserIsLockedOut($user, $setting));

    }

	public function test_if_user_locked_out_with_lower_login_limit_time_limit(): void
    {
        $user = User::factory()->create();
		
		LoginAttempt::factory()->create([
			'user_id'				=>	$user->id,
			'number_of_attempts' 	=> 	2,
			'last_attempted_at'		=>	now()->subMinutes(5)
		]);
		$setting = Setting::factory()->create([
			'login_limits_flag'		=>	1,
			'login_limits_minutes'	=>	2,
			'login_limits_attempts'	=>	3
		]);

		$this->assertFalse(LoginHelper::ifUserIsLockedOut($user, $setting));

    }

	public function test_add_new_with_no_login_attempts_row(): void{

		$user = User::factory()->create();
		$this->assertEquals(1, LoginHelper::addNew($user));

	}

	public function test_add_new_with_two_attempts_already(): void{

		
		$user = User::factory()->create();
		
		LoginAttempt::factory()->create([
			'user_id'				=>	$user->id,
			'number_of_attempts' 	=> 	2,
			'last_attempted_at'		=>	now()->subMinutes(5)
		]);

		$this->assertEquals(3, LoginHelper::addNew($user));

	}

	public function test_reset_with_no_login_attempts_found() : void{


		$user = User::factory()->create();
		$setting = Setting::factory()->create();
		LoginHelper::reset($user, $setting);

		$attempt = LoginAttempt::where('user_id', '=', $user->id)->first();

		$this->assertNull($attempt);


	}

	public function test_reset_with_login_attempts_reset() : void{


		$user = User::factory()->create();
		$setting = Setting::factory()->create([
			'login_limits_minutes'	=>	3
		]);

		LoginAttempt::factory()->create([
			'user_id'				=>	$user->id,
			'number_of_attempts' 	=> 	2,
			'last_attempted_at'		=>	now()->subMinutes(5)
		]);

		LoginHelper::reset($user, $setting);

		$attempt = LoginAttempt::where('user_id', '=', $user->id)->first();

		$this->assertNull($attempt);


	}

	public function test_reset_with_login_attempts_unable_to_reset() : void{


		$user = User::factory()->create();
		$setting = Setting::factory()->create([
			'login_limits_minutes'	=>	10
		]);

		LoginAttempt::factory()->create([
			'user_id'				=>	$user->id,
			'number_of_attempts' 	=> 	2,
			'last_attempted_at'		=>	now()->subMinutes(5)
		]);

		LoginHelper::reset($user, $setting);

		$attempt = LoginAttempt::where('user_id', '=', $user->id)->first();

		$this->assertIsObject($attempt);


	}

}
