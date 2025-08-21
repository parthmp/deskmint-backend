<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array{

        return [
            'first_name'					=>	$this->faker->text(10),
            'last_name'						=>	$this->faker->text(10),
            'tax_number'					=>	$this->faker->text(15),
            'website'						=>	$this->faker->url(),
            'email'							=>	$this->faker->email(),
            'phone'							=>	$this->faker->phoneNumber(),
            'billing_street'				=>	$this->faker->streetAddress(),
            'billing_apt'					=>	$this->faker->text(5),
            'billing_city'					=>	$this->faker->city(),
            'billing_state'					=>	$this->faker->text(8),
            'billing_postal_code'			=>	$this->faker->text(8),
            'billing_country_id'			=>	Country::inRandomOrder()->first()->id,
			'shipping_street'				=>	$this->faker->streetAddress(),
            'shipping_apt'					=>	$this->faker->text(5),
            'shipping_city'					=>	$this->faker->city(),
            'shipping_state'				=>	$this->faker->text(8),
            'shipping_postal_code'			=>	$this->faker->text(8),
            'shipping_country_id'			=>	Country::inRandomOrder()->first()->id,
            'currency_id'					=>	Currency::inRandomOrder()->first()->id,
            'payment_terms'					=>	$this->faker->randomElement([0, 7, 14, 30, 60, 90]),
            'quote_valid_days'				=>	$this->faker->randomElement([0, 7, 14, 30, 60, 90]),
            'send_reminders'				=>	$this->faker->boolean(),
            'size'							=>	$this->faker->randomElement(['1 - 3', '4 - 10', '11 - 50', '51 - 100', '101 - 500', '500+']),
            'industry_id'					=>	Industry::inRandomOrder()->first()->id
        ];

    }
}
