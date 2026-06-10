<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceSettingsGeneralControllerTest extends TestCase{
    
	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	private function getQuery($device, $queryParams, $url = '/api/manage-invoice-settings?'){

		$c = $this->set_access($device);

		$response = $this->withHeaders($c['headers'])->get($url . $queryParams);

		return $response;

	}

	public function test_if_it_fetches_data_for_general_invoice_settings_with_default_values() : void{

		$device = 'device 123';

		$company_id = $this->set_default_company();

		Storage::fake('invoice_templates');
		Storage::disk('invoice_templates')->put('test.html', 'some');
		Storage::disk('invoice_templates')->put('bla.html', 'thing');

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->getQuery($device, $params);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertEquals([
				'template'				=>	'stylish',
				'font_size'				=>	12,
				'logo_size'				=>	100,
				'primary_color'			=>	'#055f40',
				'secondary_color'		=>	'#118b65'
			], $json['settings']);
			
		$this->assertTrue(in_array('bla', $json['templates']));
		$this->assertTrue(in_array('test', $json['templates']));

		$this->assertTrue(Storage::disk('invoice_templates')->exists('test.html'));
		$this->assertTrue(Storage::disk('invoice_templates')->exists('bla.html'));

	}

	public function test_if_it_fails_data_for_general_invoice_settings_with_invalid_data_1() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings', [
			'template'				=>		'bla',
			'font_size'				=>		'bla',
			'company_id'			=>		$company_id
		], $c['headers']);

		
		$response->assertStatus((int)config('global.error_code'));

		$response = $response->json();
		

		$this->assertArrayHasKey('message', $response);
		$this->assertArrayHasKey('errors', $response);
		$this->assertEquals(2, count($response['errors']));

	}

	public function test_if_it_fails_data_for_general_invoice_settings_with_invalid_data_2() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings', [
			'template'				=>		'   ',
			'font_size'				=>		'14',
			'logo_size'				=>		'14',
			'primary_color'			=>		'14',
			'secondary_color'		=>		'14',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$response = $response->json();
		
		$this->assertArrayHasKey('message', $response);
		$this->assertArrayHasKey('errors', $response);
		$this->assertEquals(3, count($response['errors']));

	}

	public function test_if_it_fails_data_for_general_invoice_settings_with_invalid_data_3() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		Storage::fake('invoice_templates');
		Storage::disk('invoice_templates')->put('test.html', 'some');
		Storage::disk('invoice_templates')->put('bla.html', 'thing');

		$response = $this->post('/api/manage-invoice-settings', [
			'template'				=>		'bla',
			'font_size'				=>		'14',
			'logo_size'				=>		'85',
			'primary_color'			=>		'#e7e71', /* invalid hex */
			'secondary_color'		=>		'#000000',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$response = $response->json();
		$this->assertArrayHasKey('message', $response);
		$this->assertArrayHasKey('errors', $response);
		$this->assertEquals(1, count($response['errors']));

	}

	public function test_if_it_fails_data_for_general_invoice_settings_with_invalid_data_4() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		Storage::fake('invoice_templates');
		Storage::disk('invoice_templates')->put('test.html', 'some');
		Storage::disk('invoice_templates')->put('bla.html', 'thing');

		$response = $this->post('/api/manage-invoice-settings', [
			'template'				=>		'this template does not exist', /* invalid template */
			'font_size'				=>		'14',
			'logo_size'				=>		'85',
			'primary_color'			=>		'#e7e7e7',
			'secondary_color'		=>		'#000000',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_template', $response['validity']);

	}

	public function test_if_it_saves_data_for_general_invoice_settings_with_valid_data() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		Storage::fake('invoice_templates');
		Storage::disk('invoice_templates')->put('plain.html', 'some');
		Storage::disk('invoice_templates')->put('stylish.html', 'thing');

		$response = $this->post('/api/manage-invoice-settings', [
			'template'				=>		'stylish',
			'font_size'				=>		'14',
			'logo_size'				=>		'85',
			'primary_color'			=>		'#e7e7e7',
			'secondary_color'		=>		'#ffffff',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		/* now fetch to test */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings?'. $params);
		$response->assertStatus(200);
		
		$json = $response->json();
		
		$this->assertEquals([
				'template'				=>	'stylish',
				'font_size'				=>	14,
				'logo_size'				=>	85,
				'primary_color'			=>	'#e7e7e7',
				'secondary_color'		=>	'#ffffff',
				'e_invoice_on'			=>	false
			], $json['settings']);
			
		$this->assertTrue(in_array('plain', $json['templates']));
		$this->assertTrue(in_array('stylish', $json['templates']));


	}

	public function test_if_it_saves_data_for_general_invoice_settings_with_valid_data_and_e_invoice() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		Storage::fake('invoice_templates');
		Storage::disk('invoice_templates')->put('plain.html', 'some');
		Storage::disk('invoice_templates')->put('stylish.html', 'thing');

		$response = $this->post('/api/manage-invoice-settings', [
			'template'				=>		'stylish',
			'font_size'				=>		'14',
			'logo_size'				=>		'85',
			'primary_color'			=>		'#e7e7e7',
			'secondary_color'		=>		'#ffffff',
			'e_invoice'				=>		true,
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('saved_success', $response['validity']);

		/* now fetch to test */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings?'. $params);
		$response->assertStatus(200);
		
		$json = $response->json();
		
		$this->assertEquals([
				'template'				=>	'stylish',
				'font_size'				=>	14,
				'logo_size'				=>	85,
				'primary_color'			=>	'#e7e7e7',
				'secondary_color'		=>	'#ffffff',
				'e_invoice_on'			=>	true
			], $json['settings']);
			
		$this->assertTrue(in_array('plain', $json['templates']));
		$this->assertTrue(in_array('stylish', $json['templates']));
		
	}

}
