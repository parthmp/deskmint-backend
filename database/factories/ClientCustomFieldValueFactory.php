<?php

namespace Database\Factories;

use App\Models\ClientsCustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientCustomFieldValue>
 */
class ClientCustomFieldValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clients_custom_field_id'	=>	ClientsCustomField::factory(),
            'field_value'				=>	$this->faker->text(15)
        ];
    }
}
