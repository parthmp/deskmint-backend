<?php

namespace Database\Factories;

use App\Models\TwoFactorAuthToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TwoFactorAuthToken>
 */
class TwoFactorAuthTokenFactory extends Factory
{

	protected $model = TwoFactorAuthToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'		=>		User::factory(),
			'token'			=>		hash('sha512', uniqid()),
			'otp'			=>		rand(9999, 999999),
			'device'		=>		$this->faker->text(100),
			'used'			=>		$this->faker->boolean(),
			'used_at'		=>		now()->subMinutes(1)
        ];
    }
}
