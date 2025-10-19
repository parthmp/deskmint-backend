<?php

namespace Tests\Feature;

use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceSettingsNumbersControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	private function getQuery($device, $queryParams, $url = '/api/manage-invoice-settings-numbers?'){

		$c = $this->set_access($device);

		$response = $this->withHeaders($c['headers'])->get($url . $queryParams);

		return $response;

	}

	public function test_if_it_fetches_data_for_numbers_invoice_settings_with_default_values() : void{

		$device = 'device 123';

		$company_id = $this->set_default_company();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->getQuery($device, $params);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertEquals([
				'number_padding' 	=> '1',
				'reset_counter' 	=> 'never',
				'number_pattern'	=>	''
			], $json);

	}

	public function test_if_it_fails_data_for_numbers_invoice_settings_with_invalid_data_1() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_it_fails_data_for_numbers_invoice_settings_with_invalid_data_2() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'	=>	'   ',
			'reset_counter'		=>	'',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_it_fails_data_for_numbers_invoice_settings_with_invalid_number_padding() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'	=>	'invalid data',
			'reset_counter'		=>	'something',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request_data', $response['validity']);

	}

	public function test_if_it_fails_data_for_numbers_invoice_settings_with_invalid_reset_counter() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'		=>		'1',
			'reset_counter'			=>		'something',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request_data', $response['validity']);

	}

	public function test_if_it_saves_data_for_numbers_invoice_settings_with_required_data() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'		=>		'0001',
			'reset_counter'			=>		'weekly',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);


		/* now fetch to test */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-numbers?'. $params);
		$response->assertStatus(200);
		
		$json = $response->json();
		
		$this->assertEquals([
				'number_padding' 	=> '0001',
				'reset_counter' 	=> 'weekly',
				'number_pattern'	=>	''
			], $json);

	}

	public function test_if_it_saves_data_for_numbers_invoice_settings_with_all_data() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-numbers', [
			'number_padding'		=>		'0001',
			'reset_counter'			=>		'weekly',
			'number_pattern'		=>		'{$year}',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);


		/* now fetch to test */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-numbers?'. $params);
		$response->assertStatus(200);
		
		$json = $response->json();
		
		$this->assertEquals([
				'number_padding' 	=> '0001',
				'reset_counter' 	=> 'weekly',
				'number_pattern'	=>	'{$year}'
			], $json);

	}

	public function test_if_it_saves_invoice_number_reset_for_invoices(){

		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-numbers/reset-number?'. $params);
		$response->assertStatus(200);
		
		$json = $response->json();
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('reset_success', $json['validity']);
		
		$manual_reset = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', ISC_INVOICE_NUMBER_RESET_TYPE]])->first();
		
		$this->assertNotEmpty($manual_reset->settings_json);

		$settings_json = json_decode($manual_reset->settings_json, true);

		$this->assertEquals(1, (int) $settings_json['reset']);

	}

	public function test_if_it_overwrites_invoice_number_reset_for_invoices(){

		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		/* insert */
		$reset = new SettingsSection();
		$reset->company_id = $company_id;
		$reset->type = ISC_INVOICE_NUMBER_RESET_TYPE;
		$reset->settings_json = json_encode([
			'reset'	=>	'this should be overwritten by 1'
		]);
		$reset->save();

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-numbers/reset-number?'. $params);
		$response->assertStatus(200);
		
		$json = $response->json();
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('reset_success', $json['validity']);
		
		$manual_reset = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', ISC_INVOICE_NUMBER_RESET_TYPE]])->first();
		
		$this->assertNotEmpty($manual_reset->settings_json);

		$settings_json = json_decode($manual_reset->settings_json, true);

		$this->assertEquals(1, (int) $settings_json['reset']);

	}

}
