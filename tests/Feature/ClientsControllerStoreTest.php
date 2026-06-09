<?php

namespace Tests\Feature;

use App\Helpers\General;
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

class ClientsControllerStoreTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;

	public function test_if_it_stores_client_with_valid_data_with_no_custom_fields():void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id), $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname', $client->last_name);
		$this->assertEquals('123', $client->billing_postal_code);
		$this->assertEquals('1234', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state', $client->billing_state);
		$this->assertEquals('test city here s', $client->shipping_city);
		$this->assertEquals('10-50', $client->size);
		$this->assertEquals(7, $client->payment_terms);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->get();
		$this->assertEquals(2, count($client_contact_info));
		
		$this->assertEquals('test firstname 500', $client_contact_info[0]->first_name);
		$this->assertEquals('test last name 500', $client_contact_info[0]->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info[0]->email);
		$this->assertEmpty($client_contact_info[0]->phone);

		$this->assertEquals('test firstname 600', $client_contact_info[1]->first_name);
		$this->assertEquals('test last name 600', $client_contact_info[1]->last_name);
		$this->assertEquals('some@th600ing.com', $client_contact_info[1]->email);
		$this->assertEquals(1234567600, (int)$client_contact_info[1]->phone);


		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals(4, count($columns_clients_flat));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

	}

	public function test_if_it_stores_client_with_valid_data_with_no_custom_fields_with_peppol_fields() : void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();

		$data = $this->clientStoreData($currency, $country, $industry, $company_id);
		$data['personal_info']['client_company_name'] = 'abc llc';
		$data['peppol']['identifier'] = 'inc';
		$data['peppol']['scheme'] = 'sch';
		
		$response = $this->post('/api/manage-clients', $data, $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname', $client->last_name);
		$this->assertEquals('123', $client->billing_postal_code);
		$this->assertEquals('1234', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state', $client->billing_state);
		$this->assertEquals('test city here s', $client->shipping_city);
		$this->assertEquals('10-50', $client->size);
		$this->assertEquals(7, $client->payment_terms);
		$this->assertEquals('abc llc', $client->client_company_name);
		$this->assertEquals('inc', $client->peppol_identifier);
		$this->assertEquals('sch', $client->peppol_scheme);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->get();
		$this->assertEquals(2, count($client_contact_info));
		
		$this->assertEquals('test firstname 500', $client_contact_info[0]->first_name);
		$this->assertEquals('test last name 500', $client_contact_info[0]->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info[0]->email);
		$this->assertEmpty($client_contact_info[0]->phone);

		$this->assertEquals('test firstname 600', $client_contact_info[1]->first_name);
		$this->assertEquals('test last name 600', $client_contact_info[1]->last_name);
		$this->assertEquals('some@th600ing.com', $client_contact_info[1]->email);
		$this->assertEquals(1234567600, (int)$client_contact_info[1]->phone);


		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals(4, count($columns_clients_flat));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

	}


	public function test_if_it_stores_client_with_valid_data_with_all_custom_fields_123():void{
		Client::truncate();
		AccessTokenData::truncate();
		RefreshToken::truncate();
		$device = 'device 123';
		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$all_c_fields = $this->addAllCustomFields($company_id, $c['headers']);
		$order = $all_c_fields['order'];

		$temp = $this->setCustomFields();
		$custom_fields_types = $temp['types'];

		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id, $temp['fields']), $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname', $client->last_name);
		$this->assertEquals('123', $client->billing_postal_code);
		$this->assertEquals('1234', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state', $client->billing_state);
		$this->assertEquals('test city here s', $client->shipping_city);
		$this->assertEquals('10-50', $client->size);
		$this->assertEquals(7, $client->payment_terms);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($client_contact_info);
		
		$this->assertEquals('test firstname 500', $client_contact_info->first_name);
		$this->assertEquals('test last name 500', $client_contact_info->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info->email);
		$this->assertEmpty($client_contact_info->phone);
		
		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals($order, (count($columns_clients_flat)-3));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

		/* validate all inputs from clients_flat */
		$field_values = ClientCustomFieldValue::where('client_id', '=', $client->id)->orderBy('id', 'asc')->get();
		for($z = 0 ; $z < count($field_values) ; $z++){
			
			if($custom_fields_types[$z] === config('global.field_types')[0]){ /* text */
				$this->assertEquals('some text', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[1]){ /* textarea */
				$this->assertEquals('some textarea text', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[2]){ /* email */
				$this->assertEquals('email@value.com', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[3]){ /* select */
				$this->assertEquals('one', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[4]){ /* number */
				$this->assertEquals(1234678, $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[5]){ /* date */
				$this->assertEquals('2018-01-20T00:00:00.000Z', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[6]){ /* time */
				$this->assertEquals('10:15 AM', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[7]){ /* datetime */
				$this->assertEquals('2018-01-19T11:08:15Z', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[8]){ /* telephone */
				$this->assertEquals('+123457890', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[9]){ /* multiselect */
				$this->assertEquals(['one'], json_decode($field_values[$z]->field_value, true));
			}
		}

	}


	public function test_if_it_stores_client_with_valid_data_with_all_custom_fields_with_some_deletions():void{
		

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$all_c_fields = $this->addAllCustomFields($company_id, $c['headers']);
		$order = $all_c_fields['order'];

		/* create custom fields post values */
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();
		
		/* delete first three */
		$delete_ids = [];
		for($z = 0 ; $z < count($custom_fields_db) ; $z++){
			array_push($delete_ids, $custom_fields_db[$z]->id);
			if($z >= 2){
				break;
			}
		}
		
		$response = $this->delete('/api/clients-custom-fields', [
			'ids' => $delete_ids,
			'company_id' => $company_id
		], $c['headers']);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$order = ($order-count($delete_ids));

		$temp = $this->setCustomFields();
		$custom_fields_post = $temp['fields'];
		$custom_fields_types = $temp['types'];

		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id, $custom_fields_post), $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname', $client->last_name);
		$this->assertEquals('123', $client->billing_postal_code);
		$this->assertEquals('1234', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state', $client->billing_state);
		$this->assertEquals('test city here s', $client->shipping_city);
		$this->assertEquals('10-50', $client->size);
		$this->assertEquals(7, $client->payment_terms);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($client_contact_info);
		
		$this->assertEquals('test firstname 500', $client_contact_info->first_name);
		$this->assertEquals('test last name 500', $client_contact_info->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info->email);
		$this->assertEmpty($client_contact_info->phone);
		
		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals($order, (count($columns_clients_flat)-3));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

		/* validate all inputs from clients_flat */
		$field_values = ClientCustomFieldValue::where('client_id', '=', $client->id)->orderBy('id', 'asc')->get();
		for($z = 0 ; $z < count($field_values) ; $z++){
			
			if($custom_fields_types[$z] === config('global.field_types')[0]){ /* text */
				$this->assertEquals('some text', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[1]){ /* textarea */
				$this->assertEquals('some textarea text', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[2]){ /* email */
				$this->assertEquals('email@value.com', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[3]){ /* select */
				$this->assertEquals('one', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[4]){ /* number */
				$this->assertEquals(1234678, $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[5]){ /* date */
				$this->assertEquals('2018-01-20T00:00:00.000Z', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[6]){ /* time */
				$this->assertEquals('10:15 AM', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[7]){ /* datetime */
				$this->assertEquals('2018-01-19T11:08:15Z', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[8]){ /* telephone */
				$this->assertEquals('+123457890', $field_values[$z]->field_value);
			}else if($custom_fields_types[$z] === config('global.field_types')[9]){ /* multiselect */
				$this->assertEquals(['one'], json_decode($field_values[$z]->field_value, true));
			}
		}

	}


	public function test_if_it_stores_client_with_valid_data_with_all_custom_fields_with_all_deletions():void{
		

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$all_c_fields = $this->addAllCustomFields($company_id, $c['headers']);
		$order = $all_c_fields['order'];

		/* create custom fields post values */
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		/* delete first three */
		$delete_ids = [];
		for($z = 0 ; $z < count($custom_fields_db) ; $z++){
			array_push($delete_ids, $custom_fields_db[$z]->id);
		}

		$response = $this->delete('/api/clients-custom-fields', [
			'ids' => $delete_ids,
			'company_id' => $company_id
		], $c['headers']);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$order = ($order-count($delete_ids));

		$temp = $this->setCustomFields();
		$custom_fields_post = $temp['fields'];
		$custom_fields_types = $temp['types'];

		$response = $this->post('/api/manage-clients', $this->clientStoreData($currency, $country, $industry, $company_id, $custom_fields_post), $c['headers']);
		
		$response->assertStatus(200);

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('client_saved', $response['validity']);

		/* test for a few clients fields */
		$client = Client::orderBy('id', 'desc')->first();
		
		$this->assertEquals($company_id, $client->company_id);
		$this->assertEquals('test lastname', $client->last_name);
		$this->assertEquals('123', $client->billing_postal_code);
		$this->assertEquals('1234', $client->shipping_postal_code);
		$this->assertEquals($currency->id, $client->currency_id);
		$this->assertEquals($industry->id, $client->industry_id);
		$this->assertEquals('test state', $client->billing_state);
		$this->assertEquals('test city here s', $client->shipping_city);
		$this->assertEquals('10-50', $client->size);
		$this->assertEquals(7, $client->payment_terms);

		/* test for contact info */
		$client_contact_info = ClientContactInfo::where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($client_contact_info);
		
		$this->assertEquals('test firstname 500', $client_contact_info->first_name);
		$this->assertEquals('test last name 500', $client_contact_info->last_name);
		$this->assertEquals('some@th500ing.com', $client_contact_info->email);
		$this->assertEmpty($client_contact_info->phone);
		
		/* check for custom fields here */
		$columns_clients_flat = Schema::getColumnListing('clients_flat');
		$this->assertEquals($order, (count($columns_clients_flat)-3));

		/* now make sure row inserted in client_flat table */
		$clients_flat_row = DB::table('clients_flat')->where('client_id', '=', $client->id)->first();
		$this->assertNotEmpty($clients_flat_row);

		/* validate all inputs from clients_flat */
		$field_values = ClientCustomFieldValue::where('client_id', '=', $client->id)->orderBy('id', 'asc')->get();
		$this->assertEmpty($field_values);

	}

}
