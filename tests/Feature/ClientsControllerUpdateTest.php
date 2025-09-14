<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Client;
use App\Models\ClientContactInfo;
use App\Models\ClientCustomFieldValue;
use App\Models\ClientsCustomField;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomFieldType;
use App\Models\Industry;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CustomFields;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class ClientsControllerUpdateTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	private function storePostData($company_id, $fields = []){

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		return $this->clientStoreData($currency, $country, $industry, $company_id, $fields);
		
	}

	public function test_if_it_updates_the_client_with_no_custom_fields123():void{

		Client::truncate();
		ClientsCustomField::truncate();
		ClientCustomFieldValue::truncate();

		/**/
		$device = 'device 123';
		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		/**/
		/* insert */
		$response = $this->post('/api/manage-clients', $this->storePostData($company_id), $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		$client = Client::orderBy('id', 'desc')->first();
		/**/

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();

		$post_data = $this->clientStoreData($currency, $country, $industry, $company_id, [], [
			'personal_info.last_name.value'		=>	'test lastname u',
			'billing_info.postal_code.value'	=>	'1239',
			'billing_info.state.value'			=>	'test state u',
			'shipping_info.postal_code.value'	=>	'12349',
			'shipping_info.city.value'			=>	'test city here s u',
			'settings.size.value'				=>	'10-100',
			'settings.payment_terms.value'		=>	7,
			'contact_info.0.first_name.value'	=>	'test firstname 500x',
			'contact_info.0.id'					=>	1,
			'contact_info.1.id'					=>	2,
		]);
		
		$response = $this->patch('/api/manage-clients/'.$client->id, $post_data, $c['headers']);
		
		$response->assertStatus(200);
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname u', $client->last_name);
		$this->assertEquals('1239', $client->billing_postal_code);
		$this->assertEquals('12349', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state u', $client->billing_state);
		$this->assertEquals('test city here s u', $client->shipping_city);
		$this->assertEquals('10-100', $client->size);
		$this->assertEquals(7, $client->payment_terms);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->orderBy('id', 'asc')->first();
		
		$this->assertNotEmpty($client_contact_info);
		
		$this->assertEquals('test firstname 500x', $client_contact_info->first_name);
		$this->assertEquals('test last name 500', $client_contact_info->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info->email);
		$this->assertEmpty($client_contact_info->phone);
		
		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals(1, (count($columns_clients_flat)-3));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

		/* validate all inputs from clients_flat */
		$field_values = ClientCustomFieldValue::where('client_id', '=', $client->id)->orderBy('id', 'asc')->get();
		$this->assertEmpty($field_values);



	}
	
	public function test_if_it_updates_the_client_with_partial_custom_fields():void{

		Client::truncate();
		ClientsCustomField::truncate();
		ClientCustomFieldValue::truncate();

		/**/
		
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		/**/
		/* insert */
		$response = $this->post('/api/manage-clients', $this->storePostData($company_id), $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		$client = Client::orderBy('id', 'desc')->first();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		$temp_order = $this->addAllCustomFields($company_id, $c['headers'], 3);
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$fields = $this->setCustomFields();

		$post_data = $this->clientStoreData($currency, $country, $industry, $company_id, $fields['fields'], [
			'personal_info.last_name.value'		=>	'test lastname u',
			'billing_info.postal_code.value'	=>	'1239',
			'billing_info.state.value'			=>	'test state u',
			'shipping_info.postal_code.value'	=>	'12349',
			'shipping_info.city.value'			=>	'test city here s u',
			'settings.size.value'				=>	'10-100',
			'settings.payment_terms.value'		=>	7,
			'contact_info.0.first_name.value'	=>	'test firstname 500x',
			'contact_info.0.id'					=>	1,
			'contact_info.1.id'					=>	2,
		]);
		
		$response = $this->patch('/api/manage-clients/'.$client->id, $post_data, $c['headers']);
		
		$response->assertStatus(200);
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname u', $client->last_name);
		$this->assertEquals('1239', $client->billing_postal_code);
		$this->assertEquals('12349', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state u', $client->billing_state);
		$this->assertEquals('test city here s u', $client->shipping_city);
		$this->assertEquals('10-100', $client->size);
		$this->assertEquals(7, $client->payment_terms);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($client_contact_info);
		
		$this->assertEquals('test firstname 500x', $client_contact_info->first_name);
		$this->assertEquals('test last name 500', $client_contact_info->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info->email);
		$this->assertEmpty($client_contact_info->phone);
		
		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals($temp_order['order'], (count($columns_clients_flat)-3));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

		/* validate all inputs from clients_flat */
		$field_values = ClientCustomFieldValue::where('client_id', '=', $client->id)->orderBy('id', 'asc')->get();
		$this->assertEmpty($field_values);



	}


	public function test_if_it_updates_the_client_with_all_custom_fields():void{

		Client::truncate();
		ClientsCustomField::truncate();
		ClientCustomFieldValue::truncate();

		/**/
		
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		/**/
		/* insert */
		$response = $this->post('/api/manage-clients', $this->storePostData($company_id), $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		$client = Client::orderBy('id', 'desc')->first();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		$temp_order = $this->addAllCustomFields($company_id, $c['headers']);
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$fields = $this->setCustomFields();

		$post_data = $this->clientStoreData($currency, $country, $industry, $company_id, $fields['fields'], [
			'personal_info.last_name.value'		=>	'test lastname u',
			'billing_info.postal_code.value'	=>	'1239',
			'billing_info.state.value'			=>	'test state u',
			'shipping_info.postal_code.value'	=>	'12349',
			'shipping_info.city.value'			=>	'test city here s u',
			'settings.size.value'				=>	'10-100',
			'settings.payment_terms.value'		=>	7,
			'contact_info.0.first_name.value'	=>	'test firstname 500x',
			'contact_info.0.id'					=>	1,
			'contact_info.1.id'					=>	2,
		]);
		
		$response = $this->patch('/api/manage-clients/'.$client->id, $post_data, $c['headers']);
		
		$response->assertStatus(200);
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname u', $client->last_name);
		$this->assertEquals('1239', $client->billing_postal_code);
		$this->assertEquals('12349', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state u', $client->billing_state);
		$this->assertEquals('test city here s u', $client->shipping_city);
		$this->assertEquals('10-100', $client->size);
		$this->assertEquals(7, $client->payment_terms);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($client_contact_info);
		
		$this->assertEquals('test firstname 500x', $client_contact_info->first_name);
		$this->assertEquals('test last name 500', $client_contact_info->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info->email);
		$this->assertEmpty($client_contact_info->phone);
		
		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals($temp_order['order'], (count($columns_clients_flat)-3));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

		/* validate all inputs from clients_flat */
		$field_values = ClientCustomFieldValue::where('client_id', '=', $client->id)->orderBy('id', 'asc')->get();
		$this->assertEmpty($field_values);



	}
	/**/
	public function test_if_it_updates_the_client_with_all_custom_fields_and_partial_removals():void{

		Client::truncate();
		ClientsCustomField::truncate();
		ClientCustomFieldValue::truncate();

		/**/
		
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		/**/
		/* insert */
		$response = $this->post('/api/manage-clients', $this->storePostData($company_id), $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		

		$client = Client::orderBy('id', 'desc')->first();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();

		

		$temp_order = $this->addAllCustomFields($company_id, $c['headers']);

		/* remove some custom fields */
		$random_custom_field_ids = ClientsCustomField::inRandomOrder()->limit(3)->pluck('id')->toArray();
		$this->deleteClientsCustomFields($random_custom_field_ids, $c['headers'], $company_id);
		$temp_order['order'] -= 3;
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$fields = $this->setCustomFields();

		$post_data = $this->clientStoreData($currency, $country, $industry, $company_id, $fields['fields'], [
			'personal_info.last_name.value'		=>	'test lastname u',
			'billing_info.postal_code.value'	=>	'1239',
			'billing_info.state.value'			=>	'test state u',
			'shipping_info.postal_code.value'	=>	'12349',
			'shipping_info.city.value'			=>	'test city here s u',
			'settings.size.value'				=>	'10-100',
			'settings.payment_terms.value'		=>	7,
			'contact_info.0.first_name.value'	=>	'test firstname 500x',
			'contact_info.0.id'					=>	1,
			'contact_info.1.id'					=>	2,
		]);
		
		$response = $this->patch('/api/manage-clients/'.$client->id, $post_data, $c['headers']);
		
		$response->assertStatus(200);
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname u', $client->last_name);
		$this->assertEquals('1239', $client->billing_postal_code);
		$this->assertEquals('12349', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state u', $client->billing_state);
		$this->assertEquals('test city here s u', $client->shipping_city);
		$this->assertEquals('10-100', $client->size);
		$this->assertEquals(7, $client->payment_terms);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($client_contact_info);
		
		$this->assertEquals('test firstname 500x', $client_contact_info->first_name);
		$this->assertEquals('test last name 500', $client_contact_info->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info->email);
		$this->assertEmpty($client_contact_info->phone);
		
		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals($temp_order['order'], (count($columns_clients_flat)-3));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

		/* validate all inputs from clients_flat */
		$field_values = ClientCustomFieldValue::where('client_id', '=', $client->id)->orderBy('id', 'asc')->get();
		$this->assertEmpty($field_values);



	}


}
