<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Client;
use App\Models\ClientContactInfo;
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

}
