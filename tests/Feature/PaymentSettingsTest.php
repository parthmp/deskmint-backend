<?php

namespace Tests\Feature;

use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class PaymentSettingsTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	private string $url = '/api/manage-payments-settings';

	private function insertPayPalSettings(int $company_id) : void{
		
		/* insert paypal settings start */
		
		$client_id = 'abc';
		$secret = 'paypal secret';
		$mode = 'sandbox';

		$paypal_settings = new SettingsSection();
		$paypal_settings->company_id = $company_id;
		$paypal_settings->type = PAYMENTS_PAYPAL_TYPE;

		$paypal_settings->settings_json = json_encode([
			'client_id'	=>	$client_id,
			'secret'	=>	$secret,
			'mode'		=>	$mode,
		]);

		$paypal_settings->save();

		/* insert paypal settings end */

	}

	private function insertStripeSettings(int $company_id) : void{
		
		/* insert stripe settings start */
		
		$secret = 'stripe secret';

		$stripe_settings = new SettingsSection();
		$stripe_settings->company_id = $company_id;
		$stripe_settings->type = PAYMENTS_STRIPE_TYPE;
		
		$stripe_settings->settings_json = json_encode([
			'secret'	=>	$secret,
		]);

		$stripe_settings->save();

		/* insert stripe settings end */

	}

	public function test_if_fetched_data_is_empty_for_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertEmpty($json);

	}

	public function test_if_fetched_data_has_paypal_settings_for_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		SettingsSection::truncate();

		$this->insertPayPalSettings($company_id);

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertContains('paypal', $json);

	}

	public function test_if_fetched_data_has_stripe_settings_for_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		SettingsSection::truncate();

		$this->insertStripeSettings($company_id);

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertContains('stripe', $json);

	}


	public function test_if_fetched_data_has_stripe_and_paypal_settings_for_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		SettingsSection::truncate();

		$this->insertPayPalSettings($company_id);
		$this->insertStripeSettings($company_id);

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertContains('stripe', $json);
		$this->assertContains('paypal', $json);

	}

}
