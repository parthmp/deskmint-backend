<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{

	protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name'			=>	$this->faker->text(20),
			'id_number'				=>	$this->faker->word(),
			'gst_vat_number'		=>	$this->faker->word(),
			'classification'		=>	$this->faker->randomElement(['individual', 'company', 'partnership', 'trust', 'charity', 'government', 'other']),
			'website'				=>	$this->faker->url(),
			'email'					=>	$this->faker->email(),
			'phone'					=>	$this->faker->e164PhoneNumber(),
			'address_street'		=>	$this->faker->streetAddress(),
			'apt'					=>	$this->faker->word(),
			'city'					=>	$this->faker->word(),
			'state'					=>	$this->faker->word(),
			'postal_code'			=>	$this->faker->word(),
			'country_id'			=>	Country::factory(),
			'logo'					=>	$this->faker->url(),
			'invoice_terms'			=>	$this->faker->sentence(),
			'invoice_footer'		=>	$this->faker->sentence(),
			'default'				=>	$this->faker->boolean()
        ];
    }
}
