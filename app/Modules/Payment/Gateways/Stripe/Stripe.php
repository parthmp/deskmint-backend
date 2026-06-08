<?php

namespace App\Modules\Payment\Gateways\Stripe;

use App\Models\Invoice;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DatabaseOperations;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Brick\Math\RoundingMode;
use Stripe\StripeClient;

class Stripe implements PaymentGatewayInterface{

	private StripeClient $stripe_client;
	private DatabaseOperations $database_operations;
	private Invoice $invoice;
	private int $amount_in_cents;

	public function __construct(
		private string $invoice_id,
		private string $secret,
		private string $currency,
		private float $amount,
		?DatabaseOperations $database_operations = null
	){
		$this->stripe_client = new StripeClient($secret);
		$this->database_operations = $database_operations ?? new DatabaseOperations(new SettingsSectionRepository());
		$this->invoice = $this->database_operations->fetchInvoiceById($this->invoice_id);
		$this->amount_in_cents = BigDecimal::of($this->amount)->multipliedBy(100)->toScale(2, RoundingMode::HALF_UP)->toInt();
	}

	public function generateUrl() : ?string {
		
		$session = $this->stripe_client->checkout->sessions->create([
			'line_items' => [[
				'price_data' => [
					'currency' => $this->currency,
					'product_data' => [
						'name' => 'Invoice # '.$this->invoice->invoice_number,
						'description' => 'Payment for invoice # '.$this->invoice->invoice_number,
					],
					'unit_amount' => $this->amount_in_cents, // Amount in cents ($20.00)
				],
				'quantity' => 1,
			]],
			'mode' => 'payment', // Use 'subscription' for recurring billing
			'success_url' 	=> env('APP_URL').PAYMENT_SUCCESS_URL,
			'cancel_url' 	=> env('APP_URL').PAYMENT_CANCEL_URL,
		]);

		$checkout_url = $session->url;
	
		return $checkout_url;
		
	}

	public function handlePayment(array $data, Request $request): bool
	{
		throw new \Exception('Not implemented');
	}

}