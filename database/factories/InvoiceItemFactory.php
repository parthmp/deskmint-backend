<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

		$quantity = $this->faker->numberBetween(1, 15);
		$unit_price = $this->faker->randomFloat(2, 1, 1000);
		$tax_perc = $this->faker->randomFloat(2, 0, 25);

		$line_subtotal = $quantity*$unit_price;

		$tax_amount = round($line_subtotal * ($tax_perc / 100), 2);
		$line_total = round($line_subtotal + $tax_amount, 2);

        return [
            'product_id'	=>	Product::inRandomOrder()->first()->id ?? Product::factory(),
            'description'	=> $this->faker->words(3, true),
            'unit_price'	=>	$unit_price,
            'quantity'		=>	$quantity,
            'tax'			=>	$tax_perc,
            'tax_amount'	=>	$tax_amount,
            'line_total'	=>	$line_total,
            'line_subtotal'	=>	$line_subtotal
        ];
    }
}
