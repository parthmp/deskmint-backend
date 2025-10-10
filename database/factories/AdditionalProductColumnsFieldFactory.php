<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdditionalProductColumnsField>
 */
class AdditionalProductColumnsFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id'	=>	Company::factory(),
			'label'			=>	$this->faker->word(),
			'type'			=>	$this->faker->randomElement(['normal', 'tax']),
			'tax_rate'		=>	$this->faker->randomFloat(2, 1, 99),
        ];
    }
}
