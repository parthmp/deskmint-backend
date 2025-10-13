<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\CustomFieldType;
use App\Models\InvoicesCustomField;
use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoiceSettingsInvoiceDetailsControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	public function test_if_it_fails_data_for_invoice_details_invoice_settings_with_invalid_data_1() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-invoice-settings-invoice-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[]
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_it_saves_data_for_invoice_details_invoice_settings_with_for_normal_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		$array_element = [
							'id'		=>	1,
							'text'		=>	'Number',
							'value'		=>	General::replaceWithUnderscores('Number'),
							'mapped'	=>	['invoice_number'],
							'type'		=>	'normal'
						];

		$response = $this->post('/api/manage-invoice-settings-invoice-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												$array_element
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		/* now check if it was saved */
		$settings = SettingsSection::where([['type', '=', ISC_INVOICE_DETAILS_TYPE], ['company_id', '=', $company_id]])->first();
		$settings = json_decode($settings->settings_json, true);
		
		$this->assertEquals([$array_element], $settings);
	}

	public function test_if_it_saves_data_for_invoice_details_invoice_settings_with_for_custom_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		/* add invoice custom field */
		InvoicesCustomField::factory()->create([
			'id'			=>	100,
			'company_id'	=>	$company_id
		]);

		$array_element = [
							'id'						=>	1,
							'text'						=>	'test label',
							'value'						=>	General::replaceWithUnderscores('test label'),
							'mapped'					=>	'',
							'type'						=>	'custom',
							'invoices_custom_field_id'	=>	100
						];
		
		$response = $this->post('/api/manage-invoice-settings-invoice-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												$array_element
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		/* now check if it was saved */
		$settings = SettingsSection::where([['type', '=', ISC_INVOICE_DETAILS_TYPE], ['company_id', '=', $company_id]])->first();
		$settings = json_decode($settings->settings_json, true);
		
		$this->assertEquals([$array_element], $settings);
	}

	public function test_if_it_fetches_data_for_invoice_details_invoice_settings_with_both_field_types() : void{
		
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$this->setCustomFieldTypes();
		
		$fields = CustomFieldType::all();
		
		for($z = 0 ; $z < 3 ; $z++){
			
			/* add invoice custom field */

			InvoicesCustomField::factory()->create([
				'id'						=>	(100+$z),
				'label'						=>	'custom field here '.$z,
				'custom_field_type_id'		=>	$fields[$z]->id,
				'company_id'				=>	$company_id
			]);

		}
		
		$response = $this->post('/api/manage-invoice-settings-invoice-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
														'id'		=>	1,
														'text'		=>	'Number',
														'value'		=>	General::replaceWithUnderscores('Number'),
														'mapped'	=>	['invoice_number'],
														'type'		=>	'normal'
												],
												[
													'id'						=>	1,
													'text'						=>	'test label',
													'value'						=>	General::replaceWithUnderscores('test label'),
													'mapped'					=>	'',
													'type'						=>	'custom',
													'invoices_custom_field_id'	=>	100
												]
											]
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('save_success', $response['validity']);

		
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-invoice-details?'. $params);
		$json = $response->json();
		
		$this->assertEquals(8, count($json['dropdown']));
		$this->assertEquals(2, count($json['rows']));
	}


}
