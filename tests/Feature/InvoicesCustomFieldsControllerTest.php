<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\CustomFieldType;
use App\Models\InvoicesCustomField;
use App\Services\ManageFlatTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class InvoicesCustomFieldsControllerTest extends TestCase{

    use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	private function getQuery($device, $queryParams, $url = '/api/invoices-custom-fields?'){

		$c = $this->set_access($device);

		$response = $this->withHeaders($c['headers'])->get($url . $queryParams);

		return $response;

	}

	public function test_if_creating_new_invoice_custom_field_fails_invalid_data() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		InvoicesCustomField::factory()->count(15)->create();

		$response = $this->post('/api/invoices-custom-fields', [
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_data', $response['validity']);

	}

	public function test_if_creating_new_invoice_custom_field_success() : void{

		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);

		$select_field = CustomFieldType::where('input_type', '=', 'email')->first();

		InvoicesCustomField::truncate();

		$response = $this->post('/api/invoices-custom-fields', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'test label email',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('created_success', $response['validity']);

		$added_field = InvoicesCustomField::where('label', '=', 'test label email')->first();
		$this->assertNotEmpty($added_field);

		$this->assertTrue(Schema::hasColumn('invoices_flat', 'test_label_email'));

	}

	public function test_if_table_filters_for_searched_term_for_invoices(){

		$device = 'device 123';

		$company_id = $this->set_default_company();

		InvoicesCustomField::truncate();
		InvoicesCustomField::factory()->count(50)->create([
			'company_id'	=>	$company_id
		]);
		

		InvoicesCustomField::factory()->create([
			'label'			=>	'BLATEST123',
			'company_id'	=>	$company_id
		]);

		
		$queryParams = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15,
			'searched_term'		=>	'BLATEST'
		]);

		$response = $this->getQuery($device, $queryParams);

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_updating_new_invoice_custom_field_success() : void{

		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$flat_table = new ManageFlatTable('invoices_flat', 'invoices', 'invoice_id');
		$flat_table->addFlatTableColumn('past label here');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'id'			=>		10,
			'input_type'	=>		'multiselect'
		]);

		InvoicesCustomField::truncate();
		InvoicesCustomField::factory()->create([
			'id'			=>	150,
			'company_id'	=>	$company_id,
			'label'			=>	'before update'
		]);

		$this->assertFalse(Schema::hasColumn('invoices_flat', 'after_update_label'));

		$select_field = CustomFieldType::where('input_type', '=', 'multiselect')->first();

		$response = $this->patch('/api/invoices-custom-fields/150', [
			'input_field'			=>		$select_field->id,
			'label'					=>		'after update label',
			'past_label'			=>		'past label here',
			'is_required'			=>		'true',
			'add_edit_page_order'	=>		'5',
			'select_options'		=>		'one, two, three',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('updated_success', $response['validity']);

		$updated_field = InvoicesCustomField::where('label', '=', 'before update')->first();
		$this->assertEmpty($updated_field);

		$updated_field = InvoicesCustomField::where('label', '=', 'after update label')->first();
		$this->assertNotEmpty($updated_field);

		/* test for flat table */
		$this->assertTrue(Schema::hasColumn('invoices_flat', 'after_update_label'));

	}
	
	public function test_deletion_with_multiple_ids_provided() : void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('invoices_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'date'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'datetime'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'textarea'
		]);
		
		InvoicesCustomField::truncate();

		$custom_field_types = CustomFieldType::all();

		$labels = [];

		foreach($custom_field_types as $c_field_type){

			$label = 'test invoice '.$c_field_type->input_type;

			$labels[] = $label;

			$response = $this->post('/api/invoices-custom-fields', [
				'input_field'			=>		$c_field_type->id,
				'label'					=>		$label,
				'is_required'			=>		'true',
				'add_edit_page_order'	=>		'5',
				'company_id'			=>		$company_id
			], $c['headers']);
			$response->assertStatus(200);
			$this->assertArrayHasKey('validity', $response);
			$this->assertEquals('created_success', $response['validity']);
			$this->assertTrue(Schema::hasColumn('invoices_flat', General::replaceWithUnderscores($label)));
		}

		
		
		$custom_fields = InvoicesCustomField::all();

		$ids = [];
		foreach($custom_fields as $c_field){
			array_push($ids, $c_field->id);
		}

		$response = $this->delete('/api/invoices-custom-fields', [
			'ids' => $ids,
			'company_id' => $company_id
		], $c['headers']);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$deleted_fields = InvoicesCustomField::whereIn('ids', $ids)->get();

		$this->assertEmpty($deleted_fields);
		
		foreach($labels as $label){
			$this->assertFalse(Schema::hasColumn('invoices_flat', General::replaceWithUnderscores($label)));
		}

	}

	public function test_if_removing_invoice_custom_field_also_removes_field_from_invoice_details_settings_default_data(){

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('invoices_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'date'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'datetime'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'textarea'
		]);
		
		InvoicesCustomField::truncate();

		$custom_field_types = CustomFieldType::all();

		$labels = [];

		foreach($custom_field_types as $c_field_type){

			$label = 'test invoice '.$c_field_type->input_type;

			$labels[] = $label;

			$response = $this->post('/api/invoices-custom-fields', [
				'input_field'			=>		$c_field_type->id,
				'label'					=>		$label,
				'is_required'			=>		'true',
				'add_edit_page_order'	=>		'5',
				'company_id'			=>		$company_id
			], $c['headers']);
			$response->assertStatus(200);
			$this->assertArrayHasKey('validity', $response);
			$this->assertEquals('created_success', $response['validity']);
			$this->assertTrue(Schema::hasColumn('invoices_flat', General::replaceWithUnderscores($label)));
		}

		
		
		$custom_fields = InvoicesCustomField::all();

		$ids = [];
		foreach($custom_fields as $c_field){
			array_push($ids, $c_field->id);
		}

		
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-invoice-details?'. $params);
		$json = $response->json();
		
		$this->assertArrayHasKey('dropdown', $json);
		$this->assertEquals(6, count($json['dropdown']));

		$response = $this->delete('/api/invoices-custom-fields', [
			'ids' => $ids,
			'company_id' => $company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-invoice-details?'. $params);
		$json = $response->json();
		
		$this->assertArrayHasKey('dropdown', $json);
		$this->assertEquals(2, count($json['dropdown']));

	}

	public function test_if_removing_invoice_custom_field_also_removes_field_from_invoice_details_settings_saved_data(){

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Schema::dropIfExists('invoices_flat');

		CustomFieldType::truncate();
		CustomFieldType::factory()->create([
			'input_type'	=>		'email'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'date'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'datetime'
		]);

		CustomFieldType::factory()->create([
			'input_type'	=>		'textarea'
		]);
		
		InvoicesCustomField::truncate();

		$custom_field_types = CustomFieldType::all();

		$labels = [];

		foreach($custom_field_types as $c_field_type){

			$label = 'test invoice '.$c_field_type->input_type;

			$labels[] = $label;

			$response = $this->post('/api/invoices-custom-fields', [
				'input_field'			=>		$c_field_type->id,
				'label'					=>		$label,
				'is_required'			=>		'true',
				'add_edit_page_order'	=>		'5',
				'company_id'			=>		$company_id
			], $c['headers']);
			$response->assertStatus(200);
			$this->assertArrayHasKey('validity', $response);
			$this->assertEquals('created_success', $response['validity']);
			$this->assertTrue(Schema::hasColumn('invoices_flat', General::replaceWithUnderscores($label)));
		}

		
		
		$custom_fields = InvoicesCustomField::all();

		$ids = [];
		foreach($custom_fields as $c_field){
			array_push($ids, $c_field->id);
		}
		
		/* save field settings */
		$response = $this->post('/api/manage-invoice-settings-invoice-details', [
			'company_id'			=>		$company_id,
			'rows'					=>		[
												[
													'id'						=>	1,
													'text'						=>	'test label',
													'value'						=>	General::replaceWithUnderscores('test label'),
													'mapped'					=>	'',
													'type'						=>	'custom',
													'invoices_custom_field_id'	=>	2
												],
												[
													'id'						=>	1,
													'text'						=>	'test label',
													'value'						=>	General::replaceWithUnderscores('test label'),
													'mapped'					=>	'',
													'type'						=>	'custom',
													'invoices_custom_field_id'	=>	3
												]
											]
		], $c['headers']);
		
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-invoice-details?'. $params);
		$json = $response->json();
		
		$this->assertArrayHasKey('rows', $json);
		$this->assertEquals(2, count($json['rows']));

		$response = $this->delete('/api/invoices-custom-fields', [
			'ids' => [2],
			'company_id' => $company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-invoice-settings-invoice-details?'. $params);
		$json = $response->json();
		
		$this->assertArrayHasKey('rows', $json);
		$this->assertEquals(1, count($json['rows']));

	}

}
