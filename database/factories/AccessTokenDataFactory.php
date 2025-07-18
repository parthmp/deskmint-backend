<?php

namespace Database\Factories;

use App\Models\AccessTokenData;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccessTokenData>
 */
class AccessTokenDataFactory extends Factory
{
	protected $model = AccessTokenData::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            
			'token_id'			=>			1,
			'user_id'			=>			User::factory(),
			'device'			=>			$this->faker->text(10),
			'user_agent'		=>			$this->faker->text(8),
			'ip_address'		=>			$this->faker->text(15)

        ];
    }
}
