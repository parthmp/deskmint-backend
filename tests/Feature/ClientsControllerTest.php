<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\ClientsCustomField;
use App\Models\Company;
use App\Models\CustomFieldType;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientsControllerTest extends TestCase
{
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

	private function getQuery($token, $refresh_token, $device, $queryParams, $url = '/api/clients-custom-fields?'){

		$response = $this->withHeaders([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'X-Refresh-Token' => $refresh_token,
			'X-Device-Id' => $device
		])->get($url . $queryParams);

		return $response;

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

	public function test_if_it_fetches_clientS_custom_fields_without_testing_default_values(): void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$tokens = $this->set_access($user, $device);
		$token = $tokens['token'];
		$refresh_token = $tokens['refresh_token'];

		

		$this->setCustomFieldTypes();
		$this->set_default_company();

		$custom_field_types = CustomFieldType::all();
		
		$added_custom_fields = $custom_field_types->count();

		/* add one field per type to test */
		foreach($custom_field_types as $field_type){

			ClientsCustomField::factory()->create([
				'custom_field_type_id'	=>	$field_type->id,
				'company_id'			=>	1,
				'label'					=>	'client '.$field_type->input_type
			]);

		}

		

		$params = http_build_query([
			'company_id'	=>	1
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $params, '/api/manage-clients/fetch-clients-custom-fields?');
		$response = $response->json();
		
		$this->assertEquals($added_custom_fields, count($response));

	}

	public function test_if_it_fetches_clients_custom_fields_with_testing_valid_default_values(): void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$tokens = $this->set_access($user, $device);
		$token = $tokens['token'];
		$refresh_token = $tokens['refresh_token'];

		

		$this->setCustomFieldTypes();
		$this->set_default_company();

		$custom_field_types = CustomFieldType::all();
		
		$added_custom_fields = $custom_field_types->count();

		$order = 1;

		/* add one field per type to test */
		foreach($custom_field_types as $field_type){

			$default_value = '';
			$options = [];

			if($field_type->input_type === config('global.field_types')[0]){ /* text */
				$default_value = 'this is example text field';
			}else if($field_type->input_type === config('global.field_types')[1]){ /* textarea */
				$default_value = 'this is example textarea field';
			}else if($field_type->input_type === config('global.field_types')[2]){ /* email */
				$default_value = 'this@isemail.com';
			}else if($field_type->input_type === config('global.field_types')[3]){ /* select */
				$default_value = 'two';
				$options = ['one', 'two', 'three'];
			}else if($field_type->input_type === config('global.field_types')[4]){ /* number */
				$default_value = '123';
			}else if($field_type->input_type === config('global.field_types')[5]){ /* date */
				$default_value = '21 jan 2025';
			}else if($field_type->input_type === config('global.field_types')[6]){ /* time */
				$default_value = '01:45 AM';
			}else if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				$default_value = '20 jan 2025 05:04:25';
			}else if($field_type->input_type === config('global.field_types')[8]){ /* telephone */
				$default_value = '12346798';
			}else if($field_type->input_type === config('global.field_types')[9]){ /* multiselect */
				$default_value = 'one';
				$options = ['one', 'two', 'three'];
			}

			$options = implode(',', $options);

			ClientsCustomField::factory()->create([
				'custom_field_type_id'		=>	$field_type->id,
				'company_id'				=>	1,
				'label'						=>	'client '.$field_type->input_type,
				'default_value'				=>	$default_value,
				'type_params'				=>	$options,
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
		}

		$params = http_build_query([
			'company_id'	=>	1
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $params, '/api/manage-clients/fetch-clients-custom-fields?');
		$response = $response->json();
		
		$this->assertEquals($added_custom_fields, count($response));

		foreach($response as $r_field){
			
			if($r_field['custom_field_type']['input_type'] === config('global.field_types')[0]){ /* text */
				$this->assertEquals('this is example text field', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[1]){ /* textarea */
				$this->assertEquals('this is example textarea field', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[2]){ /* email */
				$this->assertEquals('this@isemail.com', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[3]){ /* select */
				$this->assertEquals('two', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[4]){ /* number */
				$this->assertEquals('123', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[5]){ /* date */
				$this->assertEquals('2025-01-21', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[6]){ /* time */
				$this->assertEquals('01:45:00', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[7]){ /* datetime */
				$this->assertEquals('2025-01-20 05:04:25', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[8]){ /* telephone */
				$this->assertEquals('12346798', $r_field['value']);
			}else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[9]){ /* multiselect */
				$this->assertEquals(['one'], $r_field['value']);
			}

		}

	}

	public function test_if_it_fetches_clientS_custom_fields_with_testing_invalid_default_values(): void{

		$user = User::factory()->create([
			'user_type'		=>		config('global.user_types.admin')
		]);
		
		$device = 'device 123';

		$tokens = $this->set_access($user, $device);
		$token = $tokens['token'];
		$refresh_token = $tokens['refresh_token'];

		

		$this->setCustomFieldTypes();
		$this->set_default_company();

		$custom_field_types = CustomFieldType::all();
		
		$added_custom_fields = $custom_field_types->count();

		$order = 1;

		/* add one field per type to test */
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
				'order_on_add_edit_page'	=>	$order
			]);
			$order++;
		}

		$params = http_build_query([
			'company_id'	=>	1
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $params, '/api/manage-clients/fetch-clients-custom-fields?');
		$response = $response->json();
		
		$this->assertEquals($added_custom_fields, count($response));

		foreach($response as $r_field){
			
			$this->assertEmpty($r_field['value']);

			// if($r_field['custom_field_type']['input_type'] === config('global.field_types')[0]){ /* text */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[1]){ /* textarea */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[2]){ /* email */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[3]){ /* select */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[4]){ /* number */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[5]){ /* date */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[6]){ /* time */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[7]){ /* datetime */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[8]){ /* telephone */
			// 	$this->assertEmpty($r_field['value']);
			// }else if($r_field['custom_field_type']['input_type'] === config('global.field_types')[9]){ /* multiselect */
			// 	$this->assertEmpty($r_field['value']);
			// }

		}

	}
}
