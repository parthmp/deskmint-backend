<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\TransactionGatewayDetail;
use App\Modules\Payment\Enums\InvoiceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Modules\Payment\Jobs\FetchStripeBalanceTransactionJob;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Mockery;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\GeneralFunctions;
use Tests\Traits\SetAccess;

class FetchStripeBalanceTransactionJobTest extends TestCase {

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

	public function test_it_throws_when_balance_transaction_not_yet_available(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$amount = 50;

		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	'abc',
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_STRIPE
		]);

		$fake_payment_intent = (object) [
			'latest_charge' => (object) [
				'balance_transaction' => null, // not available yet
			],
		];

		$mock_payment_intents = Mockery::mock();
		$mock_payment_intents->shouldReceive('retrieve')->once()->andReturn($fake_payment_intent);

		$mock_stripe_client = Mockery::mock(StripeClient::class);
		$mock_stripe_client->paymentIntents = $mock_payment_intents;

		$job = new FetchStripeBalanceTransactionJob(
			payment_intent_id: 'pi_123',
			transaction_id: $transaction->id,
			secret: 'fake-secret',
			currency: 'USD',
			stripe_client: $mock_stripe_client
		);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Balance transaction not yet available, retrying...');

		$job->handle();

		// sanity: transaction should be untouched since we never got past the throw
		$transaction->refresh();
		$this->assertNull($transaction->gateway_fees_amount);
		
	}

	public function test_it_updates_transaction_when_balance_transaction_available(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$amount = 50;

		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	'abc',
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_STRIPE
		]);
		
		$fake_balance_transaction = (object) [
			'fee' => 150,           // $1.50
			'net' => 9850,          // $98.50
			'currency' => 'usd',
			'exchange_rate' => null,
			'toArray' => fn() => ['fee' => 150, 'net' => 9850, 'currency' => 'usd'],
		];

		// real object, not stdClass, so it has a real toArray() method
		$fake_balance_transaction = new class {
			public $fee = 150;
			public $net = 9850;
			public $currency = 'usd';
			public $exchange_rate = null;
			public function toArray(): array {
				return ['fee' => 150, 'net' => 9850, 'currency' => 'usd'];
			}
		};

		$fake_payment_intent = (object) [
			'latest_charge' => (object) [
				'balance_transaction' => $fake_balance_transaction,
			],
		];

		$mock_payment_intents = Mockery::mock();
		$mock_payment_intents->shouldReceive('retrieve')->once()->andReturn($fake_payment_intent);

		$mock_stripe_client = Mockery::mock(StripeClient::class);
		$mock_stripe_client->paymentIntents = $mock_payment_intents;

		$gateway_details = new TransactionGatewayDetail();
		$gateway_details->transaction_id = $transaction->id;
		$gateway_details->payment_captured_details = json_encode(['some_existing_key' => 'value']);
		$gateway_details->save();
			

		$job = new FetchStripeBalanceTransactionJob(
			payment_intent_id: 'pi_123',
			transaction_id: $transaction->id,
			secret: 'fake-secret',
			currency: 'USD',
			stripe_client: $mock_stripe_client
		);

		$job->handle();

		$transaction->refresh();
		$this->assertEquals('1.50', $transaction->gateway_fees_amount);
		$this->assertEquals('98.50', $transaction->received_amount);

		$gateway_details->refresh();
		$captured = json_decode($gateway_details->payment_captured_details, true);
		
		$this->assertArrayHasKey('balance_transaction', $captured);
		$this->assertEquals('usd', $captured['balance_transaction']['currency']);
		$this->assertEquals('value', $captured['some_existing_key']); // confirms merge, not overwrite

	}
}
