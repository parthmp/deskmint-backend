<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomFieldType>
 */
class CustomFieldTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'input_type'	=>	$this->faker->numberBetween(1, 10),
			'input_name'	=>	$this->faker->text(10)
        ];
    }
}
