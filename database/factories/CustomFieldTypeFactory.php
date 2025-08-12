<?php

namespace Database\Factories;

use App\Helpers\General;
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
    public function definition(): array{

		$created_at = $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s');

		$key = array_rand(config('global.field_types'));
        return [
            'input_type'							=>	config('global.field_types')[$key],
			'input_name'							=>	$this->faker->text(10),
			'searchable_created_at'					=>			General::generateSearchDateText($created_at),
			'created_at'							=>			$created_at
        ];
    }
}
