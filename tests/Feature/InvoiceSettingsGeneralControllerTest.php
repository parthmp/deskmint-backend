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
				'template'				=>	'plain',
				'font_size'				=>	16,
				'logo_size'				=>	100,
				'primary_color'			=>	'#055f40',
				'secondary_color'		=>	'#118b65',
				'e_invoice_on'			=>	false
			], $json['settings']);
			
		$this->assertTrue(in_array('bla', $json['templates']));
		$this->assertTrue(in_array('test', $json['templates']));

		$this->assertTrue(Storage::disk('invoice_templates')->exists('test.html'));
		$this->assertTrue(Storage::disk('invoice_templates')->exists('bla.html'));

	}

}
