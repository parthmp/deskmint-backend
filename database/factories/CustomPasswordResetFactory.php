<?php

namespace Database\Factories;

use App\Helpers\General;
use App\Models\CustomPasswordReset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomPasswordResetToken>
 */
class CustomPasswordResetFactory extends Factory
{

	protected $model = CustomPasswordReset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            
			'user_id'		=>		User::factory(),
			'reset_code'	=>		General::generateRandomString(15),
			'device'		=>		$this->faker->string(10),
			'used'			=>		$this->faker->boolean(),
			'used_at'		=>		now()->subSeconds(30)

        ];
    }
}
