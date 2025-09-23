<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CustomFieldType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoicesCustomField>
 */
class InvoicesCustomFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array{
		
        $created_at = $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s');

        return [
            'custom_field_type_id'					=>			CustomFieldType::factory(),
            'company_id'							=>			Company::factory(),
            'label'									=>			$this->faker->text(10),
            'placeholder'							=>			'PL '.$this->faker->text(12),
            'required'								=>			$this->faker->boolean(),
            'type_params'							=>			implode(', ', $this->faker->words($this->faker->numberBetween(2, 10))),
            'default_value'							=>			$this->faker->text(5),
            'order_on_add_edit_page'				=>			$this->faker->numberBetween(1, 100),
			'created_at'							=>			$created_at
        ];
    }
}
