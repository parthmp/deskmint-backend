<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Company;
use App\Models\Country;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientsControllerStoreValidationTest extends TestCase{

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

	
    public function test_if_fails_to_store_client_with_invalid_data_tab_1_personal_info():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-clients', [
			'personal_info.first_name.value'	=>	'Jack',
			'personal_info.last_name.value'		=>	'',
			'company_id'			=>		$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab1', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_1_personal_info_2():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-clients', [
			'personal_info.first_name.value'	=>	'',
			'personal_info.last_name.value'		=>	'last name',
			'company_id'			=>		$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab1', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_1_personal_info_3():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-clients', [
			'personal_info.first_name.value'	=>	'',
			'personal_info.last_name.value'		=>	'',
			'personal_info.email.value'			=>	'not an email',
			'company_id'			=>		$company_id
		], [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab1', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_2_contact_info():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		/* valid personal info but invalid contact info */
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
					'first_name'	=>	[
						'value'		=>	''
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
					]
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

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab2', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_2_contact_info_2():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		/* valid personal info but invalid contact info */
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
					]
				],
				[
					'first_name'	=>	[
						'value'		=>	'test firstname'
					]
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

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab2', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_2_contact_info_3():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		/* valid personal info but invalid contact info */
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'something.com'
					]
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

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab2', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_billing_n_shipping_info_1():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		/* valid personal info & contact info but invalid billing and shipping info */
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
					]
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

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_billing_info_1():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		/* valid personal info & contact info but invalid billing info */
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
					]
				]
			],
			'billing_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'   '
				],
				'city'	=>	[
					'value'		=>	'test city'
				],
				'state'	=>	[
					'value'		=>	''
				],
				'postal_code'	=>	[
					'value'		=>	'123'
				],
				'country'	=>	[
					'value'		=>	5
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

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_billing_info_2():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		/* valid personal info & contact info but invalid billing info */
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
					]
				]
			],
			'billing_info'	=>	[
				'street'	=>	[
					'value'		=>	'test street'
				],
				'apt'	=>	[
					'value'		=>	'ghfg'
				],
				'city'	=>	[
					'value'		=>	'test city'
				],
				'state'	=>	[
					'value'		=>	'   '
				],
				'postal_code'	=>	[
					'value'		=>	'123'
				],
				'country'	=>	[
					'value'		=>	$country->id
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

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_shipping_info_1():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
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
					'value'		=>	'test city'
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
					'value'		=>	'test city'
				],
				'state'	=>	[
					'value'		=>	'  '
				],
				'postal_code'	=>	[
					'value'		=>	'123'
				],
				'country'	=>	[
					'value'		=>	$country->id
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

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_shipping_info_2():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
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
					'value'		=>	'test city'
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
					'value'		=>	'test city'
				],
				'state'	=>	[
					'value'		=>	'test state'
				],
				'postal_code'	=>	[
					'value'		=>	'123'
				],
				'country'	=>	[
					'value'		=>	500
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

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_billing_info_copy_to_shipping():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
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
					'value'		=>	'   '
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
			'copy_to_shipping'	=>	true,
			'company_id'	=>	$company_id
		];

		$response = $this->post('/api/manage-clients', $post_data, [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_valid_data_tab_3_billing_info_copy_to_shipping():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
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
			'copy_to_shipping'	=>	true,
			'company_id'	=>	$company_id
		];

		$response = $this->post('/api/manage-clients', $post_data, [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab4', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_valid_data_tab_3_billing_and_shipping_info():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		
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
					'first_name'	=>	[
						'value'		=>	'test firstname'
					],
					'last_name'		=>	[
						'value'		=>	'test last name'
					],
					'email'			=>	[
						'value'		=>	'some@thing.com'
					],
					'phone'			=>	[
						'value'		=>	1234567980
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
			'company_id'	=>	$company_id
		];

		$response = $this->post('/api/manage-clients', $post_data, [
        	'Accept' => 'application/json',
			'Authorization' => 'Bearer '.$token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
    	]);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab4', $response['validity']);
		
	}

}
