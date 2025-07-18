<?php

namespace Database\Factories;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoginAttempt>
 */
class LoginAttemptFactory extends Factory
{

	protected $model = LoginAttempt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
			'user_id'				=> 		User::factory(),
			'number_of_attempts'	=>		$this->faker->numberBetween(1, 5),
			'last_attempted_at'		=>		$this->faker->dateTimeBetween('-5 minutes', 'now')
        ];
    }
}
