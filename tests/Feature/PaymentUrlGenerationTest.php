<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Mockery;
use Stripe\Service\Checkout\SessionService;
use Stripe\StripeClient;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\GeneralFunctions;
use Tests\Traits\SetAccess;

class PaymentUrlGenerationTest extends TestCase
{
    use RefreshDatabase, SetAccess, DefaultCompany, CustomFields, GeneralFunctions;

	public function insertClient(int $company_id, mixed $headers, int $currency_id = 5) : Client {
		DB::table('clients')->truncate();
		$currency = Currency::where('id', '=', $currency_id)->first();
		$industry = Industry::inRandomOrder()->first();
		$country = Country::inRandomOrder()->first();
		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $headers);
		$response->assertStatus(200);
		return Client::first();
	}
	
	public function test_stripe_generate_url_creates_session_and_returns_checkout_url() {

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();
		
		$client = $this->insertClient($company_id, $c['headers']);

		$invoice = Invoice::factory()->make(['id' => 1, 'invoice_number' => 'INV-001', 'client_id' => $client->id]);

		$mock_db_ops = Mockery::mock(DatabaseOperations::class);
		$mock_db_ops->shouldReceive('fetchInvoiceById')->once()->with('1')->andReturn($invoice);

		$transaction = Transaction::factory()->make(['id' => 5]);
		$mock_db_ops->shouldReceive('insertTransaction')->once()->andReturn($transaction);
		$mock_db_ops->shouldReceive('insertPaymentUrl')->once();

		$fake_session = (object) ['url' => 'https://checkout.stripe.com/c/pay/cs_test_123'];

		$mock_session_service = Mockery::mock(SessionService::class);
		$mock_session_service->shouldReceive('create')->once()->andReturn($fake_session);

		$mock_checkout_service = Mockery::mock(CheckoutService::class);
		$mock_checkout_service->sessions = $mock_session_service;

		$mock_stripe_client = Mockery::mock(StripeClient::class);
		$mock_stripe_client->checkout = $mock_checkout_service;

		$stripe = new Stripe('1', 'fake-secret', 'USD', 100.00, $mock_db_ops, $mock_stripe_client);

		$url = $stripe->generateUrl();

		$this->assertEquals('https://checkout.stripe.com/c/pay/cs_test_123', $url);
	}

	public function test_stripe_generate_url_throws_payment_exception_on_stripe_error(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();
		
		$client = $this->insertClient($company_id, $c['headers']);
		$invoice = Invoice::factory()->make(['id' => 1, 'invoice_number' => 'INV-001', 'client_id' => $client->id]);

		$mock_db_ops = Mockery::mock(DatabaseOperations::class);
		$mock_db_ops->shouldReceive('fetchInvoiceById')->once()->andReturn($invoice);
		$mock_db_ops->shouldReceive('insertTransaction')->once()->andReturn(Transaction::factory()->make(['id' => 5]));

		$mock_session_service = Mockery::mock(SessionService::class);
		$mock_session_service->shouldReceive('create')->once()->andThrow(new \Exception('card_error'));

		$mock_checkout_service = Mockery::mock(CheckoutService::class);
		$mock_checkout_service->sessions = $mock_session_service;

		$mock_stripe_client = Mockery::mock(StripeClient::class);
		$mock_stripe_client->checkout = $mock_checkout_service;

		$stripe = new Stripe('1', 'fake-secret', 'USD', 100.00, $mock_db_ops, $mock_stripe_client);

		$this->expectException(\App\Modules\Payment\Exceptions\PaymentException::class);
		$stripe->generateUrl();
	}
}
