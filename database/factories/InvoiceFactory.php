<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentGateway;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid'									=>	Str::uuid(),
            'client_id'								=>	Client::factory(),
            'first_name'							=>	$this->faker->firstName(),
            'last_name'								=>	$this->faker->lastName(),
            'full_name'								=>	$this->faker->firstName(),
            'client_company'						=>	implode(' ', $this->faker->words(5)),
            'currency_id'							=>	5,
            'company_id'							=>	1,
            'invoice_number'						=>	$this->faker->numberBetween(1,100),
            'invoice_date'							=>	$this->faker->dateTime(),
            'due_date'								=>	$this->faker->dateTime(),
            'po_number'								=>	$this->faker->word(),
            'discount'								=>	0,
            'discount_type'							=>	0,
            'discount_amount_post_tax'				=>	0,
            'discount_amount_pre_tax'				=>	0,
            'subtotal'								=>	0,
            'tax_amount'							=>	0,
            'balance_due'							=>	0,
            'total'									=>	0,
            'refunded_amount'						=>	0,
            'status'								=>	InvoiceStatus::DRAFT->value,
            'pdf_file'								=>	'',
            'xml_file'								=>	'',
            'invoice_terms'							=>	'',
            'payment_gateway'						=>	PaymentGateway::NONE->value,
            'pattern_matched'						=>	0,
            'scan_chars'							=>	1,
            'timezone_offset_minutes'				=>	0,
            'settings_snapshot'						=>	'',
            'reminders_sent'						=>	$this->faker->dateTime(),
            'last_reminder_sent_at'					=>	$this->faker->dateTime()
        ];
    }
}
