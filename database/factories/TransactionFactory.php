<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
			'invoice_id'			=> Invoice::factory(),
			'company_id'			=> Company::factory(),
			'amount'				=> 10,
			'gateway_fees_amount'	=> 2,
			'received_amount'		=> 8,
			'payment_gateway'		=> PaymentGateway::PAYPAL->value,
			'mode'					=> 'sandbox',
			'token_id_identifier'	=> '123',
			'is_approved'			=> 0,
			'is_payment_captured'	=> 0,
			'status'				=> TransactionStatus::PENDING->value,
			'is_echeck'				=> 0,
			'paid_at'				=> null
        ];
    }
}
