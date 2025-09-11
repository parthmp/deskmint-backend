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

class ClientsControllerStoreTest extends TestCase{

	use RefreshDatabase;

    private function set_access(User $user, string $device) :Array{

		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(3599)
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	(now())->subSeconds(100)
		]);

		return [
			'token'				=>		$plain_text_token,
			'refresh_token'		=>		$refresh_token_hash
		];

	}

	private function set_default_company() : int{

		$company = Company::factory()->create([
			'company_name' 	=>  'ABC Company',
			'default'		=>	1
		]);

		return $company->id;

	}

	private function setCustomFieldTypes() : void{

		/* set custom field types */
		foreach(config('global.field_types') as $field){

			CustomFieldType::factory()->create([
				'input_type'	=>	$field,
				'input_name'	=>	'client '.$field
			]);

		}
		

	}

	public function test_if_it_stores_client_with_valid_data_with_no_custom_fields():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$post_data = [
			'personal_info'	=>	[
				'first_name'	=>	[
					'value'		=>	'test firstname'
				],
				'last_name'		=>	[
					'value'		=>	'test lastname'
				],
				'email'		=>	[
					'value'		=>	'some@thing.com'
				]
			],
			'contact_info'	=>	[
				[
					'id'			=>	500,
					'first_name'	=>	[
						'value'		=>	'test firstname 500'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 500'
					],
					'email'			=>	[
						'value'		=>	'some@th500ing.com'
					],
					'phone'			=>	[
						'value'		=>	''
					]
				],
				[
					'id'			=>	600,
					'first_name'	=>	[
						'value'		=>	'test firstname 600'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 600'
					],
					'email'			=>	[
						'value'		=>	'some@th600ing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567600
					]
				]
			],
			'billing_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'apt here'
				],
				'city'	=>	[
					'value'		=>	'test city here'
				],
				'state'	=>	[
					'value'		=>	'test state'
				],
				'postal_code'	=>	[
					'value'		=>	'123'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'shipping_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'apt here'
				],
				'city'	=>	[
					'value'		=>	'test city here s'
				],
				'state'	=>	[
					'value'		=>	'test state s'
				],
				'postal_code'	=>	[
					'value'		=>	'1234'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'custom_fields' => [],
			'settings'		=>	[
				'currency'	=>	[
					'value'	=>	$currency->id
				],
				'industry'	=>	[
					'value'	=>	$industry->id
				],
				'payment_terms'	=>	[
					'value'	=>	7
				],
				'quote_valid'	=>	[
					'value'	=>	14
				],
				'send_reminder'	=>	[
					'value'	=>	1
				],
				'size'	=>	[
					'value'	=>	'10-50'
				]
			],
			'company_id'	=>	$company_id
		];

		$response = $this->post('/api/manage-clients', $post_data, [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);
		
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


	public function test_if_it_stores_client_with_valid_data_with_all_custom_fields():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$field_types = CustomFieldType::all();
		$order = 1;
		foreach($field_types as $field_type){

			$options = '';
			
			if($field_type->input_type === config('global.field_types')[3]){
				$options = "one,two,three";
			}

			if($field_type->input_type === config('global.field_types')[9]){
				$options = "five,six,seven";
			}

			$label = 'client '.$field_type->input_type;

			$response = $this->post('/api/clients-custom-fields', [
				'input_field'			=>		$field_type->id,
				'label'					=>		$label,
				'is_required'			=>		'true',
				'add_edit_page_order'	=>		$order,
				'select_options'		=>		$options,
				'company_id'			=>		$company_id
			], [
				'Accept' => 'application/json',
				'Authorization' => 'Bearer '.$token,
				'X-Refresh-Token' => $refresh_token,
				'X-Device-Id' => $device
			]);
			
			$order++;
			$response->assertStatus(200);
			$this->assertArrayHasKey('validity', $response);
			$this->assertEquals('created_success', $response['validity']);

		}

		/* create custom fields post values */
		$custom_fields_post = [];
		$custom_fields_types = [];
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		foreach($custom_fields_db as $db_field){

			$temp_post = [];
			$temp_post['id'] = $db_field->id;
			$custom_fields_types[] = $db_field->customFieldType->input_type;
			if($db_field->customFieldType->input_type === config('global.field_types')[0]){ /* text */
				$temp_post['value'] = 'some text';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[1]){ /* textarea */
				$temp_post['value'] = 'some textarea text';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[2]){ /* email */
				$temp_post['value'] = 'email@value.com';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
				$temp_post['value'] = 'one';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[4]){ /* number */
				$temp_post['value'] = 1234678;
			}else if($db_field->customFieldType->input_type === config('global.field_types')[5]){ /* date */
				$temp_post['value'] = '2018-01-20T00:00:00.000Z';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[6]){ /* time */
				$temp_post['value'] = [
					'hours'		=>	10,
					'minutes'	=>	15,
					'seconds'	=>	10
				];
			}else if($db_field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
				$temp_post['value'] = '2018-01-19T11:08:15Z';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[8]){ /* telephone */
				$temp_post['value'] = '+123457890';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[9]){ /* multiselect */
				$temp_post['value'] = ['one'];
			}
			$custom_fields_post[] = $temp_post;
		}

		$post_data = [
			'personal_info'	=>	[
				'first_name'	=>	[
					'value'		=>	'test firstname'
				],
				'last_name'		=>	[
					'value'		=>	'test lastname'
				],
				'email'		=>	[
					'value'		=>	'some@thing.com'
				]
			],
			'contact_info'	=>	[
				[
					'id'			=>	500,
					'first_name'	=>	[
						'value'		=>	'test firstname 500'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 500'
					],
					'email'			=>	[
						'value'		=>	'some@th500ing.com'
					],
					'phone'			=>	[
						'value'		=>	''
					]
				]
			],
			'billing_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'apt here'
				],
				'city'	=>	[
					'value'		=>	'test city here'
				],
				'state'	=>	[
					'value'		=>	'test state'
				],
				'postal_code'	=>	[
					'value'		=>	'123'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'shipping_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'apt here'
				],
				'city'	=>	[
					'value'		=>	'test city here s'
				],
				'state'	=>	[
					'value'		=>	'test state s'
				],
				'postal_code'	=>	[
					'value'		=>	'1234'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'custom_fields' => $custom_fields_post,
			'settings'		=>	[
				'currency'	=>	[
					'value'	=>	$currency->id
				],
				'industry'	=>	[
					'value'	=>	$industry->id
				],
				'payment_terms'	=>	[
					'value'	=>	7
				],
				'quote_valid'	=>	[
					'value'	=>	14
				],
				'send_reminder'	=>	[
					'value'	=>	1
				],
				'size'	=>	[
					'value'	=>	'10-50'
				]
			],
			'company_id'	=>	$company_id
		];

		$response = $this->post('/api/manage-clients', $post_data, [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);
		
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
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$field_types = CustomFieldType::all();
		$order = 1;
		foreach($field_types as $field_type){

			$options = '';
			
			if($field_type->input_type === config('global.field_types')[3]){
				$options = "one,two,three";
			}

			if($field_type->input_type === config('global.field_types')[9]){
				$options = "five,six,seven";
			}

			$label = 'client '.$field_type->input_type;

			$response = $this->post('/api/clients-custom-fields', [
				'input_field'			=>		$field_type->id,
				'label'					=>		$label,
				'is_required'			=>		'true',
				'add_edit_page_order'	=>		$order,
				'select_options'		=>		$options,
				'company_id'			=>		$company_id
			], [
				'Accept' => 'application/json',
				'Authorization' => 'Bearer '.$token,
				'X-Refresh-Token' => $refresh_token,
				'X-Device-Id' => $device
			]);
			
			$order++;
			$response->assertStatus(200);
			$this->assertArrayHasKey('validity', $response);
			$this->assertEquals('created_success', $response['validity']);

		}

		/* create custom fields post values */
		$custom_fields_post = [];
		$custom_fields_types = [];
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
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$order = ($order-count($delete_ids));

		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		foreach($custom_fields_db as $db_field){

			$temp_post = [];
			$temp_post['id'] = $db_field->id;
			$custom_fields_types[] = $db_field->customFieldType->input_type;
			if($db_field->customFieldType->input_type === config('global.field_types')[0]){ /* text */
				$temp_post['value'] = 'some text';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[1]){ /* textarea */
				$temp_post['value'] = 'some textarea text';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[2]){ /* email */
				$temp_post['value'] = 'email@value.com';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
				$temp_post['value'] = 'one';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[4]){ /* number */
				$temp_post['value'] = 1234678;
			}else if($db_field->customFieldType->input_type === config('global.field_types')[5]){ /* date */
				$temp_post['value'] = '2018-01-20T00:00:00.000Z';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[6]){ /* time */
				$temp_post['value'] = [
					'hours'		=>	10,
					'minutes'	=>	15,
					'seconds'	=>	10
				];
			}else if($db_field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
				$temp_post['value'] = '2018-01-19T11:08:15Z';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[8]){ /* telephone */
				$temp_post['value'] = '+123457890';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[9]){ /* multiselect */
				$temp_post['value'] = ['one'];
			}
			$custom_fields_post[] = $temp_post;
		}

		$post_data = [
			'personal_info'	=>	[
				'first_name'	=>	[
					'value'		=>	'test firstname'
				],
				'last_name'		=>	[
					'value'		=>	'test lastname'
				],
				'email'		=>	[
					'value'		=>	'some@thing.com'
				]
			],
			'contact_info'	=>	[
				[
					'id'			=>	500,
					'first_name'	=>	[
						'value'		=>	'test firstname 500'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 500'
					],
					'email'			=>	[
						'value'		=>	'some@th500ing.com'
					],
					'phone'			=>	[
						'value'		=>	''
					]
				]
			],
			'billing_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'apt here'
				],
				'city'	=>	[
					'value'		=>	'test city here'
				],
				'state'	=>	[
					'value'		=>	'test state'
				],
				'postal_code'	=>	[
					'value'		=>	'123'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'shipping_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'apt here'
				],
				'city'	=>	[
					'value'		=>	'test city here s'
				],
				'state'	=>	[
					'value'		=>	'test state s'
				],
				'postal_code'	=>	[
					'value'		=>	'1234'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'custom_fields' => $custom_fields_post,
			'settings'		=>	[
				'currency'	=>	[
					'value'	=>	$currency->id
				],
				'industry'	=>	[
					'value'	=>	$industry->id
				],
				'payment_terms'	=>	[
					'value'	=>	7
				],
				'quote_valid'	=>	[
					'value'	=>	14
				],
				'send_reminder'	=>	[
					'value'	=>	1
				],
				'size'	=>	[
					'value'	=>	'10-50'
				]
			],
			'company_id'	=>	$company_id
		];

		$response = $this->post('/api/manage-clients', $post_data, [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);
		
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
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$field_types = CustomFieldType::all();
		$order = 1;
		foreach($field_types as $field_type){

			$options = '';
			
			if($field_type->input_type === config('global.field_types')[3]){
				$options = "one,two,three";
			}

			if($field_type->input_type === config('global.field_types')[9]){
				$options = "five,six,seven";
			}

			$label = 'client '.$field_type->input_type;

			$response = $this->post('/api/clients-custom-fields', [
				'input_field'			=>		$field_type->id,
				'label'					=>		$label,
				'is_required'			=>		'true',
				'add_edit_page_order'	=>		$order,
				'select_options'		=>		$options,
				'company_id'			=>		$company_id
			], [
				'Accept' => 'application/json',
				'Authorization' => 'Bearer '.$token,
				'X-Refresh-Token' => $refresh_token,
				'X-Device-Id' => $device
			]);
			
			$order++;
			$response->assertStatus(200);
			$this->assertArrayHasKey('validity', $response);
			$this->assertEquals('created_success', $response['validity']);

		}

		/* create custom fields post values */
		$custom_fields_post = [];
		$custom_fields_types = [];
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		/* delete first three */
		$delete_ids = [];
		for($z = 0 ; $z < count($custom_fields_db) ; $z++){
			array_push($delete_ids, $custom_fields_db[$z]->id);
		}

		$response = $this->delete('/api/clients-custom-fields', [
			'ids' => $delete_ids,
			'company_id' => $company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);
		
		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		$order = ($order-count($delete_ids));

		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		foreach($custom_fields_db as $db_field){

			$temp_post = [];
			$temp_post['id'] = $db_field->id;
			$custom_fields_types[] = $db_field->customFieldType->input_type;
			if($db_field->customFieldType->input_type === config('global.field_types')[0]){ /* text */
				$temp_post['value'] = 'some text';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[1]){ /* textarea */
				$temp_post['value'] = 'some textarea text';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[2]){ /* email */
				$temp_post['value'] = 'email@value.com';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
				$temp_post['value'] = 'one';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[4]){ /* number */
				$temp_post['value'] = 1234678;
			}else if($db_field->customFieldType->input_type === config('global.field_types')[5]){ /* date */
				$temp_post['value'] = '2018-01-20T00:00:00.000Z';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[6]){ /* time */
				$temp_post['value'] = [
					'hours'		=>	10,
					'minutes'	=>	15,
					'seconds'	=>	10
				];
			}else if($db_field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
				$temp_post['value'] = '2018-01-19T11:08:15Z';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[8]){ /* telephone */
				$temp_post['value'] = '+123457890';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[9]){ /* multiselect */
				$temp_post['value'] = ['one'];
			}
			$custom_fields_post[] = $temp_post;
		}

		$post_data = [
			'personal_info'	=>	[
				'first_name'	=>	[
					'value'		=>	'test firstname'
				],
				'last_name'		=>	[
					'value'		=>	'test lastname'
				],
				'email'		=>	[
					'value'		=>	'some@thing.com'
				]
			],
			'contact_info'	=>	[
				[
					'id'			=>	500,
					'first_name'	=>	[
						'value'		=>	'test firstname 500'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 500'
					],
					'email'			=>	[
						'value'		=>	'some@th500ing.com'
					],
					'phone'			=>	[
						'value'		=>	''
					]
				]
			],
			'billing_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'apt here'
				],
				'city'	=>	[
					'value'		=>	'test city here'
				],
				'state'	=>	[
					'value'		=>	'test state'
				],
				'postal_code'	=>	[
					'value'		=>	'123'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'shipping_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'apt here'
				],
				'city'	=>	[
					'value'		=>	'test city here s'
				],
				'state'	=>	[
					'value'		=>	'test state s'
				],
				'postal_code'	=>	[
					'value'		=>	'1234'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'custom_fields' => $custom_fields_post,
			'settings'		=>	[
				'currency'	=>	[
					'value'	=>	$currency->id
				],
				'industry'	=>	[
					'value'	=>	$industry->id
				],
				'payment_terms'	=>	[
					'value'	=>	7
				],
				'quote_valid'	=>	[
					'value'	=>	14
				],
				'send_reminder'	=>	[
					'value'	=>	1
				],
				'size'	=>	[
					'value'	=>	'10-50'
				]
			],
			'company_id'	=>	$company_id
		];

		$response = $this->post('/api/manage-clients', $post_data, [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);
		
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
