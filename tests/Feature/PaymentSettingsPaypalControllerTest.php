<?php

namespace Tests\Feature;

use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class PaymentSettingsPaypalControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	private string $url = '/api/manage-paypal-settings';

	public function test_if_it_fetches_default_settings_for_paypal_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEmpty($json['client_id']);
		$this->assertEmpty($json['secret']);
		$this->assertEmpty($json['mode']);

	}

	public function test_if_it_fails_to_save_settings_for_paypal_payments_settings_1(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id' 	=> $company_id,
			'client_id'		=>	'   ',
			'secret'		=>	'   ',
			'mode'			=>	'   '
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_request', $json['validity']);
		

	}

	public function test_if_it_fails_to_save_settings_for_paypal_payments_settings_2(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_request', $json['validity']);
		

	}

	public function test_if_it_fails_to_save_settings_for_paypal_payments_settings_3(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id' 	=> $company_id,
			'client_id'		=>	'abc 123',
			'secret'		=>	'secret api key',
			'mode'			=>	'something that is not accepted'
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('invalid_request', $json['validity']);
		

	}

	public function test_if_it_saves_settings_for_paypal_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$client_id = 'abc 123';
		$secret = 'secret api key';
		$mode = 'sandbox';

		$response = $this->post($this->url, [
			'company_id' 	=>	$company_id,
			'client_id'		=>	$client_id,
			'secret'		=>	$secret,
			'mode'			=>	$mode
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
		
		$this->assertEquals($client_id, $json['client_id']);
		$this->assertEquals($secret, $json['secret']);
		$this->assertEquals($mode, $json['mode']);

		/* test for encryption */
		$paypal_settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_PAYPAL_TYPE]])->first()->toArray();
		
		$settings_json = json_decode($paypal_settings['settings_json'], true);

		$this->assertEquals(PAYMENTS_PAYPAL_TYPE, $paypal_settings['type']);
		$this->assertEquals($client_id, $settings_json['client_id']);
		$this->assertEquals($mode, $settings_json['mode']);
		$this->assertNotEquals($secret, $settings_json['secret']);
	}


	public function test_if_it_overwrites_settings_for_paypal_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$client_id = 'abc 123';
		$secret = 'secret api key';
		$mode = 'sandbox';

		$this->post($this->url, [
			'company_id' 	=>	$company_id,
			'client_id'		=>	$client_id,
			'secret'		=>	$secret,
			'mode'			=>	$mode
		], $c['headers']);

		$response = $this->post($this->url, [
			'company_id' 	=>	$company_id,
			'client_id'		=>	$client_id.' ov',
			'secret'		=>	$secret.' ov',
			'mode'			=>	$mode
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
		
		$this->assertEquals($client_id.' ov', $json['client_id']);
		$this->assertEquals($secret.' ov', $json['secret']);
		$this->assertEquals($mode, $json['mode']);

		/* test for encryption */
		$paypal_settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', PAYMENTS_PAYPAL_TYPE]])->first()->toArray();
		
		$settings_json = json_decode($paypal_settings['settings_json'], true);

		$this->assertEquals(PAYMENTS_PAYPAL_TYPE, $paypal_settings['type']);
		$this->assertEquals($client_id.' ov', $settings_json['client_id']);
		$this->assertEquals($mode, $settings_json['mode']);
		$this->assertNotEquals($secret, $settings_json['secret']);

	}

	public function test_if_it_removes_settings_for_paypal_payments_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$client_id = 'abc 123';
		$secret = 'secret api key';
		$mode = 'sandbox';

		$response = $this->post($this->url, [
			'company_id' 	=>	$company_id,
			'client_id'		=>	$client_id,
			'secret'		=>	$secret,
			'mode'			=>	$mode
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

		$this->assertEquals($client_id, $json['client_id']);
		$this->assertEquals($secret, $json['secret']);
		$this->assertEquals($mode, $json['mode']);


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
		
		$this->assertEmpty($json['client_id']);
		$this->assertEmpty($json['secret']);
		$this->assertEmpty($json['mode']);

	}

}
