<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SettingsInvoice>
 */
class SettingsInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_name'			=>		$this->faker->randomElement(['plain', 'stylish']),
            'font_size'				=>		$this->faker->randomElement([8, 10, 12, 14, 16, 18, 20, 22, 24, 28, 30]),
            'logo_size'				=>		$this->faker->randomElement([10, 20, 30, 40, 50, 80, 100]),
            'primary_color'			=>		$this->faker->hexColor(),
            'secondary_color'		=>		$this->faker->hexColor(),
            'e_invoice_on'			=>		$this->faker->boolean(),
			/* do this later */
            'client_details_json'			=>		$this->faker->json_encode(['do this']),
            'company_details_json'			=>		$this->faker->json_encode(['do this']),
            'company_address_details_json'	=>		$this->faker->json_encode(['do this']),
            'invoice_details_json'			=>		$this->faker->json_encode(['do this']),
            'product_columns_json'			=>		$this->faker->json_encode(['do this']),
            'total_fields_json'				=>		$this->faker->json_encode(['do this']),
            'invoice_number_prefix'			=>		$this->faker->word(),
            'reset_number_prefix'			=>		$this->faker->randomElement(['monthly', 'yearly', 'weekly'])
        ];
    }
}
