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

	private function storePostData($company_id){

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

		return $post_data;
		
	}

	public function test_if_it_updates_the_client_with_no_custom_fields():void{

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
		
		$post_data = [
			'personal_info'	=>	[
				'first_name'	=>	[
					'value'		=>	'test firstname u'
				],
				'last_name'		=>	[
					'value'		=>	'test lastname u'
				],
				'email'		=>	[
					'value'		=>	'some@thing.com'
				]
			],
			'contact_info'	=>	[
				[
					'id'			=>	500,
					'first_name'	=>	[
						'value'		=>	'test firstname 500 u'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 500 u'
					],
					'email'			=>	[
						'value'		=>	'some@th500ingu.com'
					],
					'phone'			=>	[
						'value'		=>	''
					]
				],
				[
					'id'			=>	600,
					'first_name'	=>	[
						'value'		=>	'test firstname 600 u'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 600 u'
					],
					'email'			=>	[
						'value'		=>	'some@th600ingu.com'
					],
					'phone'			=>	[
						'value'		=>	1234567609
					]
				]
			],
			'billing_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street u'
				],
				'apt'	=>	[
					'value'		=>	'apt here u'
				],
				'city'	=>	[
					'value'		=>	'test city here u'
				],
				'state'	=>	[
					'value'		=>	'test state u'
				],
				'postal_code'	=>	[
					'value'		=>	'1239'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'shipping_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street u'
				],
				'apt'	=>	[
					'value'		=>	'apt here u'
				],
				'city'	=>	[
					'value'		=>	'test city here s u'
				],
				'state'	=>	[
					'value'		=>	'test state s u'
				],
				'postal_code'	=>	[
					'value'		=>	'12349'
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
					'value'	=>	'10-100'
				]
			],
			'company_id'	=>	$company_id
		];
		
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
		
		$this->assertEquals('test firstname 500', $client_contact_info->first_name);
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
		/**/

		/* add a few custom fields */



		/* */

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();
		
		$currency = Currency::inRandomOrder()->first();
		$industry = Industry::inRandomOrder()->first();
		
		$post_data = [
			'personal_info'	=>	[
				'first_name'	=>	[
					'value'		=>	'test firstname u'
				],
				'last_name'		=>	[
					'value'		=>	'test lastname u'
				],
				'email'		=>	[
					'value'		=>	'some@thing.com'
				]
			],
			'contact_info'	=>	[
				[
					'id'			=>	500,
					'first_name'	=>	[
						'value'		=>	'test firstname 500 u'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 500 u'
					],
					'email'			=>	[
						'value'		=>	'some@th500ingu.com'
					],
					'phone'			=>	[
						'value'		=>	''
					]
				],
				[
					'id'			=>	600,
					'first_name'	=>	[
						'value'		=>	'test firstname 600 u'
					],
					'last_name'		=>	[
						'value'		=>	'test last name 600 u'
					],
					'email'			=>	[
						'value'		=>	'some@th600ingu.com'
					],
					'phone'			=>	[
						'value'		=>	1234567609
					]
				]
			],
			'billing_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street u'
				],
				'apt'	=>	[
					'value'		=>	'apt here u'
				],
				'city'	=>	[
					'value'		=>	'test city here u'
				],
				'state'	=>	[
					'value'		=>	'test state u'
				],
				'postal_code'	=>	[
					'value'		=>	'1239'
				],
				'country'	=>	[
					'value'		=>	$country->id
				]
			],
			'shipping_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street u'
				],
				'apt'	=>	[
					'value'		=>	'apt here u'
				],
				'city'	=>	[
					'value'		=>	'test city here s u'
				],
				'state'	=>	[
					'value'		=>	'test state s u'
				],
				'postal_code'	=>	[
					'value'		=>	'12349'
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
					'value'	=>	'10-100'
				]
			],
			'company_id'	=>	$company_id
		];
		
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
		
		$this->assertEquals('test firstname 500', $client_contact_info->first_name);
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

}
