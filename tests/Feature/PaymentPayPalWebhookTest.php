<?php

namespace Tests\Feature;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoiceSnapshot;
use App\Models\Product;
use App\Models\SettingsSection;
use App\Models\Transaction;
use App\Models\TransactionGatewayDetail;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\GeneralFunctions;
use Tests\Traits\SetAccess;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaymentPayPalWebhookTest extends TestCase
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

	private function paypalData(bool $captured = false) : array {

		if($captured){
			$payload = file_get_contents(base_path('tests/Fixtures/paypal_captured.json'));
		}else{
			$payload = file_get_contents(base_path('tests/Fixtures/paypal_approved.json'));
		}
		
		$data = json_decode($payload, true);
		return $data;
	}

	public function test_for_approved_paypal_data_1_ppwt_002(){

		$data = $this->paypalData();
		$response = $this->post('/payments/paypal/webhook', $data);
		$this->assertEquals('Invalid data provided', $response->content());

	}

	public function test_for_approved_paypal_data_2_ppwt_002(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		$data = $this->paypalData();
		$amount = $data['resource']['purchase_units'][0]['amount']['value'];
		
		$invoice = Invoice::factory()->create([
			'uuid'			=>	'123',
			'company_id'	=>	$company_id,
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'company_id'				=>	$company_id,
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	$data['resource']['id'],
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_PAYPAL
		]);

		
		$response = $this->post('/payments/paypal/webhook', $data); //fails because of no payment settings data
		
		$this->assertStringContainsString('something_went_wrong', $response->content());
	}

	public function test_for_approved_paypal_data_3_ppwt_002(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		$data = $this->paypalData();
		$amount = $data['resource']['purchase_units'][0]['amount']['value'];

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_PAYPAL_TYPE;
		$s->settings_json = '{"mode": "sandbox", "app_id": "DeskMint US", "secret": "'.encrypt('fake secret').'", "client_id": "fake client id", "webhook_id": "webhook_id_123"}';
		$s->save();
		
		$invoice = Invoice::factory()->create([
			'company_id'	=>	$company_id,
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'company_id'	=>	$company_id,
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	$data['resource']['id'],
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_PAYPAL
		]);

		
		$response = $this->post('/payments/paypal/webhook', $data);
		
		$this->assertStringContainsString('unauthorized', $response->content());
	}

	public function test_for_approved_paypal_data_4_ppwt_002(){

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		$data = $this->paypalData();
		$amount = $data['resource']['purchase_units'][0]['amount']['value'];

		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_PAYPAL_TYPE;
		$s->settings_json = '{"mode": "sandbox", "app_id": "DeskMint US", "secret": "'.encrypt('fake secret').'", "client_id": "fake client id", "webhook_id": "webhook_id_123"}';
		$s->save();
		
		$invoice = Invoice::factory()->create([
			'company_id'	=>	$company_id,
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'company_id'	=>	$company_id,
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	$data['resource']['id'],
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_PAYPAL
		]);

		
		$mock_provider = Mockery::mock(PayPalClient::class);
		$mock_provider->shouldReceive('getAccessToken')->once()->andReturn(['access_token' => 'fake']);
		$mock_provider->shouldReceive('verifyWebHookLocally')->once()->andReturn(true);

		$paypal = new PayPal(
			company_id: $company_id,
			invoice_id: (string) $invoice->id,
			client_id: 'fake', app_id: 'fake', secret: 'fake', mode: 'sandbox',
			currency: 'USD', amount: (float) $amount,
			provider: $mock_provider
		);

		$data['webhook_id'] = 'webhook_id_123';
		$data['order_id'] = $data['resource']['id'];
		$request = Request::create('/payments/paypal/webhook', 'POST', [], [], [], [], json_encode($data));

		$this->withoutExceptionHandling();
		$this->expectException(Exception::class);
		$result = $paypal->handlePayment($data, $request);
		
	}

	public function test_for_captured_paypal_data_5_ppwt_002(){

		Storage::fake();
		Mail::fake();
		Bus::fake();

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		$data = $this->paypalData(captured:true);
		
		$amount = $data['resource']['amount']['value'];
		
		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_PAYPAL_TYPE;
		$s->settings_json = '{"mode": "sandbox", "app_id": "DeskMint US", "secret": "'.encrypt('fake secret').'", "client_id": "fake client id", "webhook_id": "webhook_id_123"}';
		$s->save();
		
		$invoice = Invoice::factory()->create([
			'company_id'	=>	$company_id,
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	$amount,
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'company_id'	=>	$company_id,
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	$data['resource']['supplementary_data']['related_ids']['order_id'],
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_PAYPAL
		]);

		
		$mock_provider = Mockery::mock(PayPalClient::class);
		$mock_provider->shouldReceive('getAccessToken')->once()->andReturn(['access_token' => 'fake']);
		$mock_provider->shouldReceive('verifyWebHookLocally')->once()->andReturn(true);

		$paypal = new PayPal(
			company_id: $company_id,
			invoice_id: (string) $invoice->id,
			client_id: 'fake', 
			app_id: 'fake', 
			secret: 'fake', 
			mode: 'sandbox',
			currency: 'USD', 
			amount: (float) $amount,
			provider: $mock_provider
		);

		$data['webhook_id'] = 'webhook_id_123';
		$data['order_id'] = $data['resource']['supplementary_data']['related_ids']['order_id'];
		
		$request = Request::create('/payments/paypal/webhook', 'POST', [], [], [], [], json_encode($data));
		$result = $paypal->handlePayment($data, $request);

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

	public function test_for_captured_paypal_data_6_ppwt_002(){

		Storage::fake();
		Mail::fake();
		Bus::fake();

		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$client = $this->insertClient($company_id, $c['headers'], 5);
		$data = $this->paypalData(captured:true);
		
		$amount = $data['resource']['amount']['value'];
		
		$s = new SettingsSection();
		$s->company_id = $company_id;
		$s->type = PAYMENTS_PAYPAL_TYPE;
		$s->settings_json = '{"mode": "sandbox", "app_id": "DeskMint US", "secret": "'.encrypt('fake secret').'", "client_id": "fake client id", "webhook_id": "webhook_id_123"}';
		$s->save();
		
		$invoice = Invoice::factory()->create([
			'company_id'	=>	$company_id,
			'uuid'			=>	'123',
			'client_id'		=>	$client->id,
			'currency_id'	=>	5,
			'status'		=>	InvoiceStatus::PENDING->value,
			'balance_due'	=>	($amount+20),
			'payment_method'=> PAYMENT_PAYPAL
		]);

		$transaction = Transaction::factory()->create([
			'company_id'	=>	$company_id,
			'invoice_id'				=>	$invoice->id,
			'amount'					=>	$amount,
			'token_id_identifier'		=>	$data['resource']['supplementary_data']['related_ids']['order_id'],
			'created_at' 				=> 	now()->subMinutes(121),
			'payment_method'			=> 	PAYMENT_PAYPAL
		]);

		
		$mock_provider = Mockery::mock(PayPalClient::class);
		$mock_provider->shouldReceive('getAccessToken')->once()->andReturn(['access_token' => 'fake']);
		$mock_provider->shouldReceive('verifyWebHookLocally')->once()->andReturn(true);

		$paypal = new PayPal(
			company_id:$company_id,
			invoice_id: (string) $invoice->id,
			client_id: 'fake', 
			app_id: 'fake', 
			secret: 'fake', 
			mode: 'sandbox',
			currency: 'USD', 
			amount: (float) $amount,
			provider: $mock_provider
		);

		$data['webhook_id'] = 'webhook_id_123';
		$data['order_id'] = $data['resource']['supplementary_data']['related_ids']['order_id'];
		
		$request = Request::create('/payments/paypal/webhook', 'POST', [], [], [], [], json_encode($data));
		$result = $paypal->handlePayment($data, $request);

		$this->assertTrue($result);

		$snapshot = InvoiceSnapshot::where('invoice_id', '=', $invoice->id)->first();
		
		$this->assertNotEmpty($snapshot->snapshot);

		Bus::assertDispatched(GenerateInvoiceJob::class);

		$gateway_details = TransactionGatewayDetail::where('transaction_id', '=', $transaction->id)->first();
		
		$this->assertNotEmpty($gateway_details->payment_captured_details);

		$f_invoice = Invoice::where('id', '=', $invoice->id)->first();
		
		$this->assertEquals(InvoiceStatus::PARTIALLY_PAID->value, (int) $f_invoice->status);
		$this->assertEquals(20, (int) $f_invoice->balance_due);
		
	}


}
