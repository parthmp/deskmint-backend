<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Client;
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
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-clients', [
			'personal_info.first_name.value'	=>	'Jack',
			'personal_info.last_name.value'		=>	'',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab1', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_1_personal_info_2():void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-clients', [
			'personal_info.first_name.value'	=>	'',
			'personal_info.last_name.value'		=>	'last name',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab1', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_1_personal_info_3():void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-clients', [
			'personal_info.first_name.value'	=>	'',
			'personal_info.last_name.value'		=>	'',
			'personal_info.email.value'			=>	'not an email',
			'company_id'			=>		$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab1', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_2_contact_info():void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab2', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_no_contact_info_1():void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab2', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_2_contact_info_2():void{
		
		
		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab2', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_2_contact_info_3():void{
		
		$device = 'device 123';
		
		$c = $this->set_access($device);
		
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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab2', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_billing_n_shipping_info_1():void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_billing_info_1():void{
		
		
		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_billing_info_2():void{
		
		
		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_shipping_info_1():void{

		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_shipping_info_2():void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_3_billing_info_copy_to_shipping():void{
		
		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab3', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_valid_data_tab_3_billing_info_copy_to_shipping():void{

		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		
		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab4', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_valid_data_tab_3_billing_and_shipping_info():void{

		$device = 'device 123';

		$c = $this->set_access($device);
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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab4', $response['validity']);
		
	}


	public function test_if_fails_to_store_client_with_invalid_data_tab_4_custom_fields():void{


		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		ClientsCustomField::truncate();

		$this->addAllCustomFields($company_id, $c['headers']);
		$this->setCustomFields();
		
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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab4', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_4_custom_fields_with_db():void{
		
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		
		$this->addAllCustomFields($company_id, $c['headers']);
		$this->setCustomFields();
		
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
		
		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab4', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_4_custom_fields_with_db_2():void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();

		$this->addAllCustomFields($company_id, $c['headers']);
		$temp = $this->setCustomFields(true); /* setting true for invalid inputs */
		$custom_fields_post = $temp['fields'];
		
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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab4', $response['validity']);
		
	}

	public function test_if_fails_to_store_client_with_invalid_data_tab_4_custom_fields_with_db_3():void{


		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();

		$this->addAllCustomFields($company_id, $c['headers']);
		$temp = $this->setCustomFields(true); /* setting true for invalid inputs */
		$custom_fields_post = $temp['fields'];

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab4', $response['validity']);
		
	}


	public function test_if_fails_to_store_client_with_valid_data_tab_4_custom_fields_with_db():void{
		Client::truncate();
		AccessTokenData::truncate();
		RefreshToken::truncate();
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();

		$this->addAllCustomFields($company_id, $c['headers']);
		$temp = $this->setCustomFields();
		$custom_fields_post = $temp['fields'];
		
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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab5', $response['validity']);
		
	}


	public function test_if_fails_to_store_client_with_invalid_data_tab_5_settings_1():void{
		
		
		$device = 'device 123';

		$c = $this->set_access($device);
		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();

		$this->addAllCustomFields($company_id, $c['headers']);
		$temp = $this->setCustomFields();
		$custom_fields_post = $temp['fields'];
		
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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab5', $response['validity']);
		
	}


	public function test_if_fails_to_store_client_with_invalid_data_tab_5_settings_2():void{
		
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$country = Country::inRandomOrder()->first();
		$this->setCustomFieldTypes();

		$this->addAllCustomFields($company_id, $c['headers']);
		$temp = $this->setCustomFields();
		$custom_fields_post = $temp['fields'];

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab5', $response['validity']);
		
	}


	public function test_if_fails_to_store_client_with_invalid_data_tab_4_no_custom_fields():void{

		$device = 'device 123';

		$c = $this->set_access($device);

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

		$response = $this->post('/api/manage-clients', $post_data, $c['headers']);
		
		$response->assertStatus((int)config('global.error_code'));

		$this->arrayHasKey('validity', $response);
		$this->assertEquals('invalid_data_tab5', $response['validity']);
		
	}


}
