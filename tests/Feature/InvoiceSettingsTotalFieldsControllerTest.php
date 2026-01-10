<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceSettingsTotalFieldsControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	public function test_if_it_fails_data_for_total_fields_invoice_settings_with_invalid_data_1() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-total-fields', [
			'company_id'			=>		$company_id,
			'rows'					=>		[]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(1, count($json['errors']));

	}

	public function test_if_it_saves_data_for_total_fields_invoice_settings_with_for_normal_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$array_element = [
							'id'		=>	1,
							'text'		=>	'Net Subtotal',
							'value'		=>	General::replaceWithUnderscores('Net Subtotal'),
							'mapped'	=>	['net_subtotal'],
							'type'		=>	'normal'
						];

		$response = $this->post('/api/manage-invoice-settings-total-fields', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												$array_element
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		/* now check if it was saved */
		$settings = SettingsSection::where([['type', '=', ISC_INVOICE_TOTAL_FIELDS_TYPE], ['company_id', '=', $company_id]])->first();
		$settings = json_decode($settings->settings_json, true);
		
		$this->assertEquals([$array_element], $settings);
	}

	public function test_if_it_overwrites_data_for_total_fields_invoice_settings() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();
		
		$response = $this->post('/api/manage-invoice-settings-total-fields', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'		=>	1,
													'text'		=>	'Net Subtotal',
													'value'		=>	General::replaceWithUnderscores('Net Subtotal'),
													'mapped'	=>	['net_subtotal'],
													'type'		=>	'normal'
												],
												[
													'id'		=>	2,
													'text'		=>	'Subtotal',
													'value'		=>	General::replaceWithUnderscores('Subtotal'),
													'mapped'	=>	['sub_total'],
													'type'		=>	'normal'
												],
												[
													'id'		=>	3,
													'text'		=>	'Discount',
													'value'		=>	General::replaceWithUnderscores('Discount'),
													'mapped'	=>	['discount'],
													'type'		=>	'normal'
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		/* now check if it was saved */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-total-fields?'. $params);
		$json = $response->json();
		$this->assertEquals('Net Subtotal', $json['rows'][0]['text']);
		$this->assertEquals('Subtotal', $json['rows'][1]['text']);
		$this->assertEquals('Discount', $json['rows'][2]['text']);
		
		
		$response = $this->post('/api/manage-invoice-settings-total-fields', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'		=>	2,
													'text'		=>	'Subtotal overwritten',
													'value'		=>	General::replaceWithUnderscores('Subtotal'),
													'mapped'	=>	['sub_total'],
													'type'		=>	'normal'
												]
											]
		], $c['headers']);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-total-fields?'. $params);
		$json = $response->json();
		
		$this->assertEquals('Subtotal overwritten', $json['rows'][0]['text']);
		

	}

}
