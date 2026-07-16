<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Jobs\SendEmailJob;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\PaymentUrl;
use App\Models\SettingsSection;
use App\Models\Transaction;
use App\Modules\Payment\Enums\InvoiceStatus;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\GeneralFunctions;
use Tests\Traits\SetAccess;

class PaymentControllerTest extends TestCase
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

	public function test_if_it_shows_payment_page_1_pct_001(){

		$this->withoutExceptionHandling();
		$this->expectException(\Illuminate\Routing\Exceptions\InvalidSignatureException::class);
		$response = $this->get('/pay-invoice/123');

	}

	public function test_if_it_shows_payment_page_2_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'	=>	'123',
			'client_id'	=>	$client->id,
			'currency_id'	=>	5
		]);

		$this->withoutExceptionHandling();
		$this->expectException(\Illuminate\Routing\Exceptions\InvalidSignatureException::class);

		$response = $this->get('/pay-invoice/123');
	}

	public function test_if_it_shows_payment_page_3_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5
		]);

		$url = URL::signedRoute('invoice.pay', ['uuid' => $invoice->uuid]);

		$payment_method_name = General::getPaymentMethodName((int) $invoice->payment_method);

		$is_paid = ((int) InvoiceStatus::PAID->value === (int) $invoice->status);
		$is_cancelled = ((int) InvoiceStatus::CANCELLED->value === (int) $invoice->status);
		
		$response = $this->get($url);
		$response->assertStatus(200);
		$response->assertViewIs('payment.payment_page');
		$response->assertViewHas('is_paid', $is_paid);
		$response->assertViewHas('is_cancelled', $is_cancelled);
		$response->assertViewHas('invoice', $invoice);
		$response->assertViewHas('payment_method_name', $payment_method_name);

	}

	public function test_if_it_generates_gateway_url_4_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PAID->value
		]);

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		$response->assertStatus(200);

		$due_date = General::formatDateTime($invoice->due_date, $invoice->timezone_offset_minutes);
		$response->assertViewIs('payment.payment_page');
		$response->assertViewHas('checkout_url', '');
		$response->assertViewHas('due_date', $due_date);
		
	}

	public function test_if_it_generates_gateway_url_5_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::CANCELLED->value
		]);

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		$response->assertStatus(200);

		$due_date = General::formatDateTime($invoice->due_date, $invoice->timezone_offset_minutes);
		$response->assertViewIs('payment.payment_page');
		$response->assertViewHas('checkout_url', '');
		$response->assertViewHas('due_date', $due_date);
		
	}

	public function test_if_it_generates_gateway_url_6_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		
		$response->assertStatus(302);
		$this->assertEquals('https://testurl.com', $response->headers->get('location'));
		$response->assertRedirect('https://testurl.com');
		
	}

	public function test_if_it_generates_gateway_url_7_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10,
			'created_at' 	=> now()->subMinutes(119)
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		
		$response->assertStatus(302);
		$this->assertEquals('https://testurl.com', $response->headers->get('location'));
		$response->assertRedirect('https://testurl.com');
		
	}

	public function test_if_it_generates_gateway_url_8_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10,
			'created_at' 	=> now()->subMinutes(121)
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$this->withoutExceptionHandling();
		$this->expectException(Exception::class);
		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		
		
	}

	public function test_if_it_generates_gateway_url_9_pct_001(){

		Bus::fake();
		Mail::fake();

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10,
			'created_at' 	=> now()->subMinutes(121)
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_PAYPAL_TYPE;
		$s->settings_json = '{"mode": "sandbox", "app_id": "DeskMint US", "secret": "'.encrypt('fake secret').'", "client_id": "fake client id", "webhook_id": "webhook_id_123"}';
		$s->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		$response->assertStatus(302);
		$this->assertEquals(env('APP_URL').'/pay-invoice/failure/'.PAYMENT_PAYPAL, $response->headers->get('location'));
		$response->assertRedirect(env('APP_URL').'/pay-invoice/failure/'.PAYMENT_PAYPAL);

		Bus::assertDispatched(SendEmailJob::class);		
		
	}

	public function test_if_it_generates_gateway_url_10_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10,
			'created_at' 	=> now()->subMinutes(121)
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_PAYPAL_TYPE;
		$s->settings_json = '{"mode": "sandbox", "app_id": "'.env('TEST_PAYPAL_APP_ID').'", "secret": "'.encrypt(env('TEST_PAYPAL_SECRET_KEY')).'", "client_id": "'.env('TEST_PAYPAL_CLIENT_ID').'", "webhook_id": "'.env('TEST_PAYPAL_WEBHOOK_ID').'"}';
		$s->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		$response->assertStatus(302);

		$fetched_url = Transaction::where('invoice_id', '=', $invoice->id)->orderBy('id', 'desc')->with('payment_url')->first();
		
		$this->assertEquals($fetched_url->payment_url->url, $response->headers->get('location'));
		
		$response->assertRedirect($fetched_url->payment_url->url);
		
		
	}

	//for stripe
	public function test_if_it_generates_gateway_stripe_url_11_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PAID->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		
		$this->assertStringContainsString('This invoice is already paid. No further action is needed', $response->content());
		
	}

	public function test_if_it_generates_gateway_stripe_url_12_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::CANCELLED->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		
		$this->assertStringContainsString('This invoice is cancelled. No further action is needed', $response->content());
		
	}

	public function test_if_it_generates_gateway_url_stripe_13_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10,
			'created_at' 	=> now()->subMinutes(121)
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$this->withoutExceptionHandling();
		$this->expectException(Exception::class);
		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		
		
	}

	public function test_if_it_generates_gateway_url_stripe_14_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10,
			'created_at' 	=> now()->subMinutes(119),
			'payment_method' => PAYMENT_STRIPE
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		
		$response->assertStatus(302);
		$this->assertEquals('https://testurl.com', $response->headers->get('location'));
		$response->assertRedirect('https://testurl.com');
		
	}

	public function test_if_it_generates_gateway_url_stripe_15_pct_001(){

		Bus::fake();
		Mail::fake();

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10,
			'created_at' 	=> now()->subMinutes(121),
			'payment_method'=> PAYMENT_STRIPE
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_STRIPE_TYPE;
		$s->settings_json = '{"secret": "'.encrypt('fake-secret').'", "webhook_secret": "'.encrypt('fake-webhook-secret').'"}';
		$s->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		$response->assertStatus(302);
		$this->assertEquals(env('APP_URL').'/pay-invoice/failure/'.PAYMENT_STRIPE, $response->headers->get('location'));
		$response->assertRedirect(env('APP_URL').'/pay-invoice/failure/'.PAYMENT_STRIPE);

		Bus::assertDispatched(SendEmailJob::class);		
		
	}

	public function test_if_it_generates_gateway_url_stripe_16_pct_001(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	10,
			'payment_method'=> PAYMENT_STRIPE
		]);

		$transaction = Transaction::factory()->create([
			'invoice_id'	=>	$invoice->id,
			'amount'		=>	10,
			'created_at' 	=> now()->subMinutes(121),
			'payment_method'=> PAYMENT_STRIPE
		]);

		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction->id;
		$payment_url->gateway_url_identifier = $transaction->token_id_identifier;
		$payment_url->url = 'https://testurl.com';
		$payment_url->save();

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_STRIPE_TYPE;
		$s->settings_json = '{"secret": "'.encrypt(env('TEST_STRIPE_SECRET_KEY')).'", "webhook_secret": "'.env('TEST_STRIPE_WEBHOOK_SECRET_KEY').'"}';
		$s->save();

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);
		
		$response = $this->post($checkout_url);
		$response->assertStatus(302);

		$fetched_url = Transaction::where('invoice_id', '=', $invoice->id)->orderBy('id', 'desc')->with('payment_url')->first();
		
		$this->assertEquals($fetched_url->payment_url->url, $response->headers->get('location'));
		
		$response->assertRedirect($fetched_url->payment_url->url);
		
		
	}



}
