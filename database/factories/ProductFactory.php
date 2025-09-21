<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
			'company_id'			=>	Company::factory(),
            'product_name'			=>	$this->faker->words(5),
            'price'					=>	$this->faker->randomFloat(2, 0.50, 500),
			'sku'					=>	$this->faker->word(),
			'description'			=>	$this->faker->paragraph()
        ];
    }
}
