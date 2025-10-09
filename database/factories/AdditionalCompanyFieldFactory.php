<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class AdditionalCompanyFieldFactory extends Factory
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
            'label'			=>	implode(' ', $this->faker->words(2)),
			'value'			=>	implode(' ', $this->faker->words(2))
        ];
    }
}
