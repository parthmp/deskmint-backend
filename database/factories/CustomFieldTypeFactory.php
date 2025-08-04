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
		$key = array_rand(config('global.field_types'));
        return [
            'input_type'	=>	config('global.field_types')[$key],
			'input_name'	=>	$this->faker->text(10)
        ];
    }
}
