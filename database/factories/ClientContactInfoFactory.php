<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientContactInfo>
 */
class ClientContactInfoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
			'client_id'		=> Client::factory(),
            'first_name' 	=> $this->faker->firstName(),
            'last_name' 	=> $this->faker->lastName(),
            'email' 		=> $this->faker->email(),
            'phone' 		=> $this->faker->phoneNumber()
        ];
    }
}
