<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
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
use Tests\TestCase;
use Tests\Traits\SetAccess;
use Tests\Traits\DefaultCompany;
use Tests\Traits\CustomFields;

class ClientsControllerStoreValidationTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany, CustomFields;
	
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

	public function test_if_fails_to_store_client_with_no_contact_info_1():void{
		
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
			'contact_info'	=>	[],
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
					'id'			=>	500,
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
					'id'			=>	500,
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
					'id'			=>	500,
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
					'id'			=>	500,
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
					'id'			=>	500,
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
					'id'			=>	500,
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
					'id'			=>	500,
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
					'id'			=>	500,
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


	public function test_if_fails_to_store_client_with_invalid_data_tab_4_custom_fields():void{
		
		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$access = $this->set_access($user, $device);

		$token = $access['token'];
		$refresh_token = $access['refresh_token'];

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		ClientsCustomField::truncate();

		CustomFieldType::truncate();
		$this->setCustomFieldTypes();
		$custom_field_types = CustomFieldType::all();
		
		/* add one field per type to test */
		$order = 1;
		foreach($custom_field_types as $field_type){

			$default_value = '';
			$options = [];

			if($field_type->input_type === config('global.field_types')[0]){ /* text */
				$default_value = '    ';
			}else if($field_type->input_type === config('global.field_types')[1]){ /* textarea */
				$default_value = '  ';
			}else if($field_type->input_type === config('global.field_types')[2]){ /* email */
				$default_value = 'thisisnotemail.com';
			}else if($field_type->input_type === config('global.field_types')[3]){ /* select */
				$default_value = 'something';
				$options = ['one', 'two', 'three'];
			}else if($field_type->input_type === config('global.field_types')[4]){ /* number */
				$default_value = 'abc';
			}else if($field_type->input_type === config('global.field_types')[5]){ /* date */
				$default_value = 'this is not date';
			}else if($field_type->input_type === config('global.field_types')[6]){ /* time */
				$default_value = 'this is not time';
			}else if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				$default_value = 'this is not timestamp';
			}else if($field_type->input_type === config('global.field_types')[8]){ /* telephone */
				$default_value = 'this is not telephone';
			}else if($field_type->input_type === config('global.field_types')[9]){ /* multiselect */
				$default_value = 'something else';
				$options = ['one', 'two', 'three'];
			}

			$options = implode(',', $options);
			
			ClientsCustomField::factory()->create([
				'id'						=>	$order,
				'custom_field_type_id'		=>	$field_type->id,
				'company_id'				=>	1,
				'label'						=>	'client '.$field_type->input_type,
				'default_value'				=>	$default_value,
				'type_params'				=>	$options,
				'required'					=>	1,
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
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
			'custom_fields'	=>	'invalid custom fields data',
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

	public function test_if_fails_to_store_client_with_invalid_data_tab_4_custom_fields_with_db():void{
		
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

		$custom_field_types = CustomFieldType::all();

		/* add one field per type to test */
		$order = 1;
		foreach($custom_field_types as $field_type){

			$default_value = '';
			$options = [];

			if($field_type->input_type === config('global.field_types')[0]){ /* text */
				$default_value = '    ';
			}else if($field_type->input_type === config('global.field_types')[1]){ /* textarea */
				$default_value = '  ';
			}else if($field_type->input_type === config('global.field_types')[2]){ /* email */
				$default_value = 'thisisnotemail.com';
			}else if($field_type->input_type === config('global.field_types')[3]){ /* select */
				$default_value = 'something';
				$options = ['one', 'two', 'three'];
			}else if($field_type->input_type === config('global.field_types')[4]){ /* number */
				$default_value = 'abc';
			}else if($field_type->input_type === config('global.field_types')[5]){ /* date */
				$default_value = 'this is not date';
			}else if($field_type->input_type === config('global.field_types')[6]){ /* time */
				$default_value = 'this is not time';
			}else if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				$default_value = 'this is not timestamp';
			}else if($field_type->input_type === config('global.field_types')[8]){ /* telephone */
				$default_value = 'this is not telephone';
			}else if($field_type->input_type === config('global.field_types')[9]){ /* multiselect */
				$default_value = 'something else';
				$options = ['one', 'two', 'three'];
			}

			$options = implode(',', $options);

			ClientsCustomField::factory()->create([
				'custom_field_type_id'		=>	$field_type->id,
				'company_id'				=>	1,
				'label'						=>	'client '.$field_type->input_type,
				'default_value'				=>	$default_value,
				'type_params'				=>	$options,
				'required'					=>	1,
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
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
			'custom_fields'	=>	[ /* only one custom field added, others are not which means it fails */
				[
					'id'		=>	1,
					'value'		=>	'something',
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

	public function test_if_fails_to_store_client_with_invalid_data_tab_4_custom_fields_with_db_2():void{
		
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

		$custom_field_types = CustomFieldType::all();

		/* add one field per type to test */
		$order = 1;
		foreach($custom_field_types as $field_type){

			$default_value = '';
			$options = [];

			if($field_type->input_type === config('global.field_types')[0]){ /* text */
				$default_value = '    ';
			}else if($field_type->input_type === config('global.field_types')[1]){ /* textarea */
				$default_value = '  ';
			}else if($field_type->input_type === config('global.field_types')[2]){ /* email */
				$default_value = 'thisisnotemail.com';
			}else if($field_type->input_type === config('global.field_types')[3]){ /* select */
				$default_value = 'something';
				$options = ['one', 'two', 'three'];
			}else if($field_type->input_type === config('global.field_types')[4]){ /* number */
				$default_value = 'abc';
			}else if($field_type->input_type === config('global.field_types')[5]){ /* date */
				$default_value = 'this is not date';
			}else if($field_type->input_type === config('global.field_types')[6]){ /* time */
				$default_value = 'this is not time';
			}else if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				$default_value = 'this is not timestamp';
			}else if($field_type->input_type === config('global.field_types')[8]){ /* telephone */
				$default_value = 'this is not telephone';
			}else if($field_type->input_type === config('global.field_types')[9]){ /* multiselect */
				$default_value = 'something else';
				$options = ['one', 'two', 'three'];
			}

			$options = implode(',', $options);

			ClientsCustomField::factory()->create([
				'id'						=>	$order,
				'custom_field_type_id'		=>	$field_type->id,
				'company_id'				=>	1,
				'label'						=>	'client '.$field_type->input_type,
				'default_value'				=>	$default_value,
				'type_params'				=>	$options,
				'required'					=>	1,
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
		}

		/* create custom fields post values */
		$custom_fields_post = [];
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		foreach($custom_fields_db as $db_field){

			$temp_post = [];
			$temp_post['id'] = $db_field->id;

			if($db_field->customFieldType->input_type === config('global.field_types')[0]){ /* text */
				$temp_post['value'] = 'text value';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[1]){ /* textarea */
				$temp_post['value'] = '  ';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[2]){ /* email */
				$temp_post['value'] = 'not an email@value.com';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
				$temp_post['value'] = 'one';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[4]){ /* number */
				$temp_post['value'] = 1234678;
			}else if($db_field->customFieldType->input_type === config('global.field_types')[5]){ /* date */
				$temp_post['value'] = '20 jan 2018 invalid';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[6]){ /* time */
				$temp_post['value'] = 'invalid time';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
				$temp_post['value'] = '19 jan 2018 11:08:15 invalid';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[8]){ /* telephone */
				$temp_post['value'] = '+123457890';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[9]){ /* multiselect */
				$temp_post['value'] = ['one', 'two'];
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
			'custom_fields' => $custom_fields_post,
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

	public function test_if_fails_to_store_client_with_invalid_data_tab_4_custom_fields_with_db_3():void{
		
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

		$custom_field_types = CustomFieldType::all();

		/* add one field per type to test */
		$order = 1;
		foreach($custom_field_types as $field_type){

			$default_value = '';
			$options = [];

			if($field_type->input_type === config('global.field_types')[0]){ /* text */
				$default_value = '    ';
			}else if($field_type->input_type === config('global.field_types')[1]){ /* textarea */
				$default_value = '  ';
			}else if($field_type->input_type === config('global.field_types')[2]){ /* email */
				$default_value = 'thisisnotemail.com';
			}else if($field_type->input_type === config('global.field_types')[3]){ /* select */
				$default_value = 'something';
				$options = ['one', 'two', 'three'];
			}else if($field_type->input_type === config('global.field_types')[4]){ /* number */
				$default_value = 'abc';
			}else if($field_type->input_type === config('global.field_types')[5]){ /* date */
				$default_value = 'this is not date';
			}else if($field_type->input_type === config('global.field_types')[6]){ /* time */
				$default_value = 'this is not time';
			}else if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				$default_value = 'this is not timestamp';
			}else if($field_type->input_type === config('global.field_types')[8]){ /* telephone */
				$default_value = 'this is not telephone';
			}else if($field_type->input_type === config('global.field_types')[9]){ /* multiselect */
				$default_value = 'something else';
				$options = ['one', 'two', 'three'];
			}

			$options = implode(',', $options);

			ClientsCustomField::factory()->create([
				'id'						=>	$order,
				'custom_field_type_id'		=>	$field_type->id,
				'company_id'				=>	1,
				'label'						=>	'client '.$field_type->input_type,
				'default_value'				=>	$default_value,
				'type_params'				=>	$options,
				'required'					=>	1,
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
		}

		/* create custom fields post values */
		$custom_fields_post = [];
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		foreach($custom_fields_db as $db_field){

			$temp_post = [];
			$temp_post['id'] = $db_field->id;

			if($db_field->customFieldType->input_type === config('global.field_types')[0]){ /* text */
				$temp_post['value'] = '  ';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[1]){ /* textarea */
				$temp_post['value'] = '  ';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[2]){ /* email */
				$temp_post['value'] = 'not an email@value.com';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
				$temp_post['value'] = '  ';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[4]){ /* number */
				$temp_post['value'] = 1234678;
			}else if($db_field->customFieldType->input_type === config('global.field_types')[5]){ /* date */
				$temp_post['value'] = '20 jan 2018 invalid';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[6]){ /* time */
				$temp_post['value'] = [
					'hours'		=>	10,
					'minutes'	=>	15,
					'seconds'	=>	10
				];
			}else if($db_field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
				$temp_post['value'] = '19 jan 2018 11:08:15 invalid';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[8]){ /* telephone */
				$temp_post['value'] = '+123457890';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[9]){ /* multiselect */
				$temp_post['value'] = [];
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
			'custom_fields' => $custom_fields_post,
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


	public function test_if_fails_to_store_client_with_valid_data_tab_4_custom_fields_with_db():void{
		
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

		$custom_field_types = CustomFieldType::all();

		/* add one field per type to test */
		$order = 1;
		foreach($custom_field_types as $field_type){

			$default_value = '';
			$options = [];

			if($field_type->input_type === config('global.field_types')[0]){ /* text */
				$default_value = '    ';
			}else if($field_type->input_type === config('global.field_types')[1]){ /* textarea */
				$default_value = '  ';
			}else if($field_type->input_type === config('global.field_types')[2]){ /* email */
				$default_value = 'thisisnotemail.com';
			}else if($field_type->input_type === config('global.field_types')[3]){ /* select */
				$default_value = 'something';
				$options = ['one', 'two', 'three'];
			}else if($field_type->input_type === config('global.field_types')[4]){ /* number */
				$default_value = 'abc';
			}else if($field_type->input_type === config('global.field_types')[5]){ /* date */
				$default_value = 'this is not date';
			}else if($field_type->input_type === config('global.field_types')[6]){ /* time */
				$default_value = 'this is not time';
			}else if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				$default_value = 'this is not timestamp';
			}else if($field_type->input_type === config('global.field_types')[8]){ /* telephone */
				$default_value = 'this is not telephone';
			}else if($field_type->input_type === config('global.field_types')[9]){ /* multiselect */
				$default_value = 'something else';
				$options = ['one', 'two', 'three'];
			}

			$options = implode(',', $options);

			ClientsCustomField::factory()->create([
				'id'						=>	$order,
				'custom_field_type_id'		=>	$field_type->id,
				'company_id'				=>	1,
				'label'						=>	'client '.$field_type->input_type,
				'default_value'				=>	$default_value,
				'type_params'				=>	$options,
				'required'					=>	1,
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
		}

		/* create custom fields post values */
		$custom_fields_post = [];
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		foreach($custom_fields_db as $db_field){

			$temp_post = [];
			$temp_post['id'] = $db_field->id;

			if($db_field->customFieldType->input_type === config('global.field_types')[0]){ /* text */
				$temp_post['value'] = 'text value';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[1]){ /* textarea */
				$temp_post['value'] = 'textarea here';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[2]){ /* email */
				$temp_post['value'] = 'email@value.com';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
				$temp_post['value'] = 'one';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[4]){ /* number */
				$temp_post['value'] = 1234678;
			}else if($db_field->customFieldType->input_type === config('global.field_types')[5]){ /* date */
				$temp_post['value'] = '20 jan 2018';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[6]){ /* time */
				$temp_post['value'] = [
					'hours'		=>	10,
					'minutes'	=>	15,
					'seconds'	=>	10
				];
			}else if($db_field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
				$temp_post['value'] = '19 jan 2018 11:08:15';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[8]){ /* telephone */
				$temp_post['value'] = '+123457890';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[9]){ /* multiselect */
				$temp_post['value'] = ['one', 'two'];
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
			'custom_fields' => $custom_fields_post,
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
		$this->assertEquals('invalid_data_tab5', $response['validity']);
		
	}


	public function test_if_fails_to_store_client_with_invalid_data_tab_5_settings_1():void{
		
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

		$custom_field_types = CustomFieldType::all();

		/* add one field per type to test */
		$order = 1;
		foreach($custom_field_types as $field_type){

			$default_value = '';
			$options = [];

			if($field_type->input_type === config('global.field_types')[0]){ /* text */
				$default_value = '    ';
			}else if($field_type->input_type === config('global.field_types')[1]){ /* textarea */
				$default_value = '  ';
			}else if($field_type->input_type === config('global.field_types')[2]){ /* email */
				$default_value = 'thisisnotemail.com';
			}else if($field_type->input_type === config('global.field_types')[3]){ /* select */
				$default_value = 'something';
				$options = ['one', 'two', 'three'];
			}else if($field_type->input_type === config('global.field_types')[4]){ /* number */
				$default_value = 'abc';
			}else if($field_type->input_type === config('global.field_types')[5]){ /* date */
				$default_value = 'this is not date';
			}else if($field_type->input_type === config('global.field_types')[6]){ /* time */
				$default_value = 'this is not time';
			}else if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				$default_value = 'this is not timestamp';
			}else if($field_type->input_type === config('global.field_types')[8]){ /* telephone */
				$default_value = 'this is not telephone';
			}else if($field_type->input_type === config('global.field_types')[9]){ /* multiselect */
				$default_value = 'something else';
				$options = ['one', 'two', 'three'];
			}

			$options = implode(',', $options);

			ClientsCustomField::factory()->create([
				'id'						=>	$order,
				'custom_field_type_id'		=>	$field_type->id,
				'company_id'				=>	1,
				'label'						=>	'client '.$field_type->input_type,
				'default_value'				=>	$default_value,
				'type_params'				=>	$options,
				'required'					=>	1,
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
		}

		/* create custom fields post values */
		$custom_fields_post = [];
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		foreach($custom_fields_db as $db_field){

			$temp_post = [];
			$temp_post['id'] = $db_field->id;

			if($db_field->customFieldType->input_type === config('global.field_types')[0]){ /* text */
				$temp_post['value'] = 'text value';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[1]){ /* textarea */
				$temp_post['value'] = 'textarea here';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[2]){ /* email */
				$temp_post['value'] = 'email@value.com';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
				$temp_post['value'] = 'one';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[4]){ /* number */
				$temp_post['value'] = 1234678;
			}else if($db_field->customFieldType->input_type === config('global.field_types')[5]){ /* date */
				$temp_post['value'] = '20 jan 2018';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[6]){ /* time */
				$temp_post['value'] = [
					'hours'		=>	10,
					'minutes'	=>	15,
					'seconds'	=>	10
				];
			}else if($db_field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
				$temp_post['value'] = '19 jan 2018 11:08:15';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[8]){ /* telephone */
				$temp_post['value'] = '+123457890';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[9]){ /* multiselect */
				$temp_post['value'] = ['one', 'two'];
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
			'custom_fields' => $custom_fields_post,
			'settings'		=>	[
				'currency'	=>	[
					'value'	=>	800
				],
				'industry'	=>	[
					'value'	=>	800
				],
				'payment_terms'	=>	[
					'value'	=>	'   '
				],
				'quote_valid'	=>	[
					'value'	=>	'   '
				],
				'send_reminder'	=>	[
					'value'	=>	500
				],
				'size'	=>	[
					'value'	=>	'   '
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
		$this->assertEquals('invalid_data_tab5', $response['validity']);
		
	}


	public function test_if_fails_to_store_client_with_invalid_data_tab_5_settings_2():void{
		
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

		$custom_field_types = CustomFieldType::all();

		/* add one field per type to test */
		$order = 1;
		foreach($custom_field_types as $field_type){

			$default_value = '';
			$options = [];

			if($field_type->input_type === config('global.field_types')[0]){ /* text */
				$default_value = '    ';
			}else if($field_type->input_type === config('global.field_types')[1]){ /* textarea */
				$default_value = '  ';
			}else if($field_type->input_type === config('global.field_types')[2]){ /* email */
				$default_value = 'thisisnotemail.com';
			}else if($field_type->input_type === config('global.field_types')[3]){ /* select */
				$default_value = 'something';
				$options = ['one', 'two', 'three'];
			}else if($field_type->input_type === config('global.field_types')[4]){ /* number */
				$default_value = 'abc';
			}else if($field_type->input_type === config('global.field_types')[5]){ /* date */
				$default_value = 'this is not date';
			}else if($field_type->input_type === config('global.field_types')[6]){ /* time */
				$default_value = 'this is not time';
			}else if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				$default_value = 'this is not timestamp';
			}else if($field_type->input_type === config('global.field_types')[8]){ /* telephone */
				$default_value = 'this is not telephone';
			}else if($field_type->input_type === config('global.field_types')[9]){ /* multiselect */
				$default_value = 'something else';
				$options = ['one', 'two', 'three'];
			}

			$options = implode(',', $options);

			ClientsCustomField::factory()->create([
				'id'						=>	$order,
				'custom_field_type_id'		=>	$field_type->id,
				'company_id'				=>	1,
				'label'						=>	'client '.$field_type->input_type,
				'default_value'				=>	$default_value,
				'type_params'				=>	$options,
				'required'					=>	1,
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
		}

		/* create custom fields post values */
		$custom_fields_post = [];
		$custom_fields_db = ClientsCustomField::whereHas('customFieldType')->with('customFieldType')->get();

		foreach($custom_fields_db as $db_field){

			$temp_post = [];
			$temp_post['id'] = $db_field->id;

			if($db_field->customFieldType->input_type === config('global.field_types')[0]){ /* text */
				$temp_post['value'] = 'text value';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[1]){ /* textarea */
				$temp_post['value'] = 'textarea here';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[2]){ /* email */
				$temp_post['value'] = 'email@value.com';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
				$temp_post['value'] = 'one';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[4]){ /* number */
				$temp_post['value'] = 1234678;
			}else if($db_field->customFieldType->input_type === config('global.field_types')[5]){ /* date */
				$temp_post['value'] = '20 jan 2018';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[6]){ /* time */
				$temp_post['value'] = [
					'hours'		=>	10,
					'minutes'	=>	15,
					'seconds'	=>	10
				];
			}else if($db_field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
				$temp_post['value'] = '19 jan 2018 11:08:15';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[8]){ /* telephone */
				$temp_post['value'] = '+123457890';
			}else if($db_field->customFieldType->input_type === config('global.field_types')[9]){ /* multiselect */
				$temp_post['value'] = ['one', 'two'];
			}
			$custom_fields_post[] = $temp_post;
		}

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
					'value'	=>	'   '
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
		$this->assertEquals('invalid_data_tab5', $response['validity']);
		
	}


	public function test_if_fails_to_store_client_with_invalid_data_tab_4_no_custom_fields():void{
		
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
					'value'	=>	'   '
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
		$this->assertEquals('invalid_data_tab5', $response['validity']);
		
	}


}
