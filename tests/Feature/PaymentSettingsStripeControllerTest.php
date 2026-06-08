<?php

namespace Tests\Feature;

use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class PaymentSettingsStripeControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	private string $url = '/api/manage-stripe-settings';

	public function test_if_it_fetches_default_settings_for_stripe_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEmpty($json['secret']);

	}

	public function test_if_it_fails_to_save_settings_for_stripe_payments_settings_1(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id' 	=> $company_id,
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(2, count($json['errors']));
		
	}


	public function test_if_it_fails_to_save_settings_for_stripe_payments_settings_2(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id' 	=>  $company_id,
			'secret'		=>	''
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(2, count($json['errors']));
		
	}

	public function test_if_it_fails_to_save_settings_for_stripe_payments_settings_3(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id' 			=>  $company_id,
			'secret'				=>	'',
			'webhook_secret'		=>	''
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(2, count($json['errors']));
		
	}


	public function test_if_it_saves_settings_for_stripe_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$secret = 'secret api key';
		$webhook_secret = 'secret webhook api key';

		$response = $this->post($this->url, [
			'company_id' 			=>  $company_id,
			'secret'				=>	$secret,
			'webhook_secret'		=>	$webhook_secret,
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);


		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals($secret, $json['secret']);
		$this->assertEquals($webhook_secret, $json['webhook_secret']);

		/* test for encryption */
		$stripe_settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_STRIPE_TYPE]])->first()->toArray();
		
		$settings_json = json_decode($stripe_settings['settings_json'], true);

		$this->assertEquals(PAYMENTS_STRIPE_TYPE, $stripe_settings['type']);
		$this->assertNotEquals($secret, $settings_json['secret']);
		$this->assertNotEquals($webhook_secret, $settings_json['webhook_secret']);
	}


	public function test_if_it_overwrites_settings_for_stripe_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$secret = 'secret api key';
		$webhook_secret = 'webhook secret api key';

		$this->post($this->url, [
			'company_id' 			=>	$company_id,
			'secret'				=>	$secret,
			'webhook_secret'		=>	$webhook_secret
		], $c['headers']);

		$response = $this->post($this->url, [
			'company_id' 			=>	$company_id,
			'secret'				=>	$secret.' ov',
			'webhook_secret'		=>	$webhook_secret.' ov'
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);


		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals($secret.' ov', $json['secret']);
		$this->assertEquals($webhook_secret.' ov', $json['webhook_secret']);

		/* test for encryption */
		$stripe_settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_STRIPE_TYPE]])->first()->toArray();
		
		$settings_json = json_decode($stripe_settings['settings_json'], true);

		$this->assertEquals(PAYMENTS_STRIPE_TYPE, $stripe_settings['type']);
		$this->assertNotEquals($secret, $settings_json['secret']);
		$this->assertNotEquals($webhook_secret, $settings_json['webhook_secret']);

	}

	public function test_if_it_removes_settings_for_stripe_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$secret = 'secret api key';
		$webhook_secret = 'webhook secret api key';

		$response = $this->post($this->url, [
			'company_id' 			=>	$company_id,
			'secret'				=>	$secret,
			'webhook_secret'		=>	$webhook_secret
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertEquals($secret, $json['secret']);

		$response = $this->delete($this->url, [
			'company_id' 	=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('removed_success', $json['validity']);

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEmpty($json['secret']);
		$this->assertEmpty($json['webhook_secret']);
	}

}
