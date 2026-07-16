<?php

namespace Tests\Feature;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoiceSnapshot;
use App\Models\SettingsSection;
use App\Models\Transaction;
use App\Models\TransactionGatewayDetail;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\GeneralFunctions;
use Tests\Traits\SetAccess;

class PaymentStripeWebhookTest extends TestCase
{
    use RefreshDatabase, SetAccess, DefaultCompany, CustomFields, GeneralFunctions;

	public function insertClient(int $company_id, mixed $headers, int $currency_id = 5) : Client {
		DB::table('clients')->truncate();
		$currency = Currency::where('id', '=', 5)->first();
		$industry = Industry::inRandomOrder()->first();
		$country = Country::inRandomOrder()->first();
		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $headers);
		$response->assertStatus(200);
		return Client::first();
	}

	private function stripeData() : array {

		$payload = file_get_contents(base_path('tests/Fixtures/stripe_completed.json'));
		$data = json_decode($payload, true);
		return $data;

	}

	public function test_for_stripe_completed_data_1_pswt_002(){

		$data = $this->stripeData();
		$response = $this->post('/payments/stripe/webhook', $data);
		$this->assertEquals('Invalid data provided', $response->content());

	}

	public function test_for_stripe_completed_data_2_pswt_002(){

		$data = $this->stripeData();
		$response = $this->post('/payments/stripe/webhook', $data);
		$this->assertEquals('Invalid data provided', $response->content());

	}

	public function test_for_stripe_completed_data_3_pswt_002(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		$data = $this->stripeData();
		
		$amount = (int) $data['data']['object']['amount_total'];
		$amount = BigDecimal::of($amount)->dividedBy(100, 2, RoundingMode::HalfUp)->toFloat();

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_STRIPE_TYPE;
		$s->settings_json = '{"secret": "'.encrypt('fake-secret').'", "webhook_secret": "'.encrypt('fake-webhook-secret').'"}';
		$s->save();
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	$data['data']['object']['metadata']['payment_id'],
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_STRIPE
		]);

		
		$response = $this->post('/payments/stripe/webhook', $data);
		$this->assertStringContainsString('unauthorized', $response->content());

	}

	public function test_for_stripe_completed_data_4_pswt_002(){

		Storage::fake();
		Mail::fake();
		Bus::fake();

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		$data = $this->stripeData();
		
		$amount = (int) $data['data']['object']['amount_total'];
		$amount = BigDecimal::of($amount)->dividedBy(100, 2, RoundingMode::HalfUp)->toFloat();

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_STRIPE_TYPE;
		$s->settings_json = '{"secret": "'.encrypt('fake-secret').'", "webhook_secret": "'.encrypt('fake-webhook-secret').'"}';
		$s->save();
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	$data['data']['object']['metadata']['payment_id'],
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_STRIPE
		]);

		// fake balance transaction data, since fetching a real one requires hitting Stripe's real API
		$fake_balance_transaction = (object) [
			'fee' 				=> 150,
			'net' 				=> (int) BigDecimal::of($amount)->multipliedBy(100)->toInt() - 150,
			'currency' 			=> 'usd',
			'exchange_rate' 	=> null,
		];

		$fake_payment_intent = (object) [
			'latest_charge' => (object) [
				'balance_transaction' => $fake_balance_transaction,
			],
		];

		$mock_payment_intents = Mockery::mock();
		$mock_payment_intents->shouldReceive('retrieve')->once()->andReturn($fake_payment_intent);

		$mock_stripe_client = Mockery::mock(StripeClient::class);
		$mock_stripe_client->paymentIntents = $mock_payment_intents;

		$stripe = Mockery::mock(Stripe::class, [(string) $invoice->id, 'fake', 'USD', (float) $amount, new DatabaseOperations(new SettingsSectionRepository()), $mock_stripe_client])->makePartial();
		$stripe->shouldAllowMockingProtectedMethods();
		$stripe->shouldReceive('verifyAuthenticity')->once()->andReturn(true);

		$stripe->setWebhookSecret('fake-webhook-secret');

		$data['webhook_id'] = 'webhook_id_123';
		$data['order_id'] = $data['data']['object']['metadata']['payment_id'];
		
		$request = Request::create('/payments/stripe/webhook', 'POST', [], [], [], [], json_encode($data));
		$result = $stripe->handlePayment($data, $request);

		$this->assertTrue($result);

		$snapshot = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		
		$this->assertNotEmpty($snapshot->snapshot);

		Bus::assertDispatched(GenerateInvoiceJob::class);

		$gateway_details = TransactionGatewayDetail::where('transaction_id', '=', $transaction->id)->first();
		
		$this->assertNotEmpty($gateway_details->payment_captured_details);

		$f_invoice = Invoice::where('id', '=', $invoice->id)->first();
		
		$this->assertEquals(InvoiceStatus::PAID->value, (int) $f_invoice->status);
		$this->assertEquals(0, (int) $f_invoice->balance_due);
		
	}

	public function test_for_stripe_completed_data_5_pswt_002(){

		Storage::fake();
		Mail::fake();
		Bus::fake();

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		$data = $this->stripeData();
		
		$amount = (int) $data['data']['object']['amount_total'];
		$amount = BigDecimal::of($amount)->dividedBy(100, 2, RoundingMode::HalfUp)->toFloat();

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_STRIPE_TYPE;
		$s->settings_json = '{"secret": "'.encrypt('fake-secret').'", "webhook_secret": "'.encrypt('fake-webhook-secret').'"}';
		$s->save();
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount+25,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	$data['data']['object']['metadata']['payment_id'],
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_STRIPE
		]);

		// fake balance transaction data, since fetching a real one requires hitting Stripe's real API
		$fake_balance_transaction = (object) [
			'fee' 				=> 150,
			'net' 				=> (int) BigDecimal::of($amount)->multipliedBy(100)->toInt() - 150,
			'currency' 			=> 'usd',
			'exchange_rate' 	=> null,
		];

		$fake_payment_intent = (object) [
			'latest_charge' => (object) [
				'balance_transaction' => $fake_balance_transaction,
			],
		];

		$mock_payment_intents = Mockery::mock();
		$mock_payment_intents->shouldReceive('retrieve')->once()->andReturn($fake_payment_intent);

		$mock_stripe_client = Mockery::mock(StripeClient::class);
		$mock_stripe_client->paymentIntents = $mock_payment_intents;

		$stripe = Mockery::mock(Stripe::class, [(string) $invoice->id, 'fake', 'USD', (float) $amount, new DatabaseOperations(new SettingsSectionRepository()), $mock_stripe_client])->makePartial();
		$stripe->shouldAllowMockingProtectedMethods();
		$stripe->shouldReceive('verifyAuthenticity')->once()->andReturn(true);

		$stripe->setWebhookSecret('fake-webhook-secret');

		$data['webhook_id'] = 'webhook_id_123';
		$data['order_id'] = $data['data']['object']['metadata']['payment_id'];
		
		$request = Request::create('/payments/stripe/webhook', 'POST', [], [], [], [], json_encode($data));
		$result = $stripe->handlePayment($data, $request);

		$this->assertTrue($result);

		$snapshot = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		
		$this->assertNotEmpty($snapshot->snapshot);

		Bus::assertDispatched(GenerateInvoiceJob::class);

		$gateway_details = TransactionGatewayDetail::where('transaction_id', '=', $transaction->id)->first();
		
		$this->assertNotEmpty($gateway_details->payment_captured_details);

		$f_invoice = Invoice::where('id', '=', $invoice->id)->first();
		
		$this->assertEquals(InvoiceStatus::PARTIALLY_PAID->value, (int) $f_invoice->status);
		$this->assertEquals(25, (int) $f_invoice->balance_due);
		
	}

}
