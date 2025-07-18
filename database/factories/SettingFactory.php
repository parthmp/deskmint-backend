<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{

	protected $model = Setting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'login_limits_flag'		=>		$this->faker->boolean(),
            'login_limits_attempts'	=>		$this->faker->numberBetween(1, 5),
            'login_limits_minutes'	=>		$this->faker->numberBetween(2, 15),
            'two_factor_auth_flag'	=>		$this->faker->boolean(),
            'login_email_flag'		=>		$this->faker->boolean()
        ];
    }
}
