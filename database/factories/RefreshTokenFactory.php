<?php

namespace Database\Factories;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RefreshToken>
 */
class RefreshTokenFactory extends Factory
{	

	protected $model = RefreshToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            
			'user_id'			=>			User::factory(),
			'refresh_token'		=>			$this->faker->text(100),
			'device'			=>			$this->faker->text(10),
			'used'				=>			$this->faker->boolean(),
			'used_at'			=>			now()->subMinute(1)
        ];
    }
}
