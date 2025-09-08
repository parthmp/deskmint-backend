<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\ClientsCustomField;
use App\Models\Company;
use App\Models\CustomFieldType;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use DateTime;
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

	public function test_if_it_fetches_clients_custom_fields_with_testing_invalid_default_values(): void{

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

	public function test_if_it_fetches_clients_custom_fields_with_valid_date_formats(): void{

		$date_formats = [
			'Y-m-d',        // 2025-08-19
			'd-M-Y',        // 01-Jan-2025  
			'j-M-Y',        // 1-Jan-2025
			'd M Y',        // 21 Jan 2025 (with leading zeros)
			'j M Y',        // 1 Jan 2025 (without leading zeros)
		];
		

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

		$order = 1;
		ClientsCustomField::truncate();
		
		$dates_to_check = [];
		
		foreach($custom_field_types as $field_type){

			if($field_type->input_type === config('global.field_types')[5]){ /* date */
				
				for($z = 0 ; $z < count($date_formats) ; $z++){

					$start = Carbon::create(2010, 1, 1)->timestamp;
					$end   = Carbon::create(2025, 12, 31)->timestamp;

					$random_date = Carbon::createFromTimestamp(rand($start, $end));
					$formatted_date = $random_date->format($date_formats[$z]);
					
					$dates_to_check[] = [
						'format'	=>	$date_formats[$z],
						'date' 		=> 	$formatted_date
					];
					
					ClientsCustomField::factory()->create([
						'custom_field_type_id'		=>	$field_type->id,
						'company_id'				=>	1,
						'label'						=>	'client '.$z.''.$field_type->input_type,
						'default_value'				=>	$formatted_date,
						'order_on_add_edit_page'	=>	$order,
						'type_params'				=>	''
					]);

					$order++;

				}

			}

		
			
		}

		$params = http_build_query([
			'company_id'	=>	1
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $params, '/api/manage-clients/fetch-clients-custom-fields?');
		$response = $response->json();
		
		for($z = 0 ; $z < count($dates_to_check); $z++){

        	$carbon_date = DateTime::createFromFormat($dates_to_check[$z]['format'], $dates_to_check[$z]['date']);
            $temp_date = $carbon_date->format('Y-m-d');

        	$this->assertEquals($temp_date, $response[$z]['value']);

        }

	}

	public function test_if_it_fetches_clients_custom_fields_with_invalid_date_formats(): void{

		$invalid_dates = [
			// Ambiguous slash formats (removed from your list)
			'02/10/2025',       // Could be Feb 10 or Oct 2
			'15/03/2024',       // European format  
			'03/15/2024',       // US format
			'12/25/2023',       // US Christmas
			
			// Wrong separators
			'2025.08.19',       // Dots instead of dashes
			'01_Jan_2025',      // Underscores
			'1|Jan|2025',       // Pipes
			'21,Jan,2025',      // Commas
			
			// Wrong month format
			'01-January-2025',  // Full month name instead of abbreviated
			'1-January-2025',   // Full month name
			'21 January 2025',  // Full month name with space
			'01-01-2025',       // Numeric month instead of name
			'1-1-2025',         // Numeric month, no leading zeros
			
			// Wrong day format  
			'1st-Jan-2025',     // Ordinal numbers
			'21st Jan 2025',    // Ordinal numbers with space
			'01st-Jan-2025',    // Mixed leading zero with ordinal
			
			// Wrong year format
			'01-Jan-25',        // 2-digit year
			'1-Jan-25',         // 2-digit year, no leading zero
			'21 Jan 25',        // 2-digit year with space
			
			// Time included (not supported in date-only formats)
			'2025-08-19 14:30', // ISO with time
			'01-Jan-2025 2:30PM', // With time
			'1 Jan 2025 14:30',   // With 24h time
			
			// Completely wrong formats
			'January 21, 2025',   // US long format
			'21st of January 2025', // British long format
			'Jan 21 2025',        // US short without separator
			'2025/01/21',         // ISO with slashes
			'20250121',           // No separators
			
			// Invalid dates (should overflow or fail)
			'32-Jan-2025',        // Invalid day
			'01-Xyz-2025',        // Invalid month
			'21 Foo 2025',        // Invalid month  
			'00-Jan-2025',        // Zero day
			'1-Jan-0000',         // Zero year
			
			// Empty/malformed
			'',                   // Empty string
			'   ',                // Just spaces
			'not a date',         // Plain text
			'2025',               // Just year
			'Jan 2025',           // Month and year only
			'21-Jan',             // Day and month only
		];
				

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

		$order = 1;
		ClientsCustomField::truncate();
		
		$dates_to_check = [];
		
		foreach($custom_field_types as $field_type){

			if($field_type->input_type === config('global.field_types')[5]){ /* date */
				
				for($z = 0 ; $z < count($invalid_dates) ; $z++){

					$start = Carbon::create(2010, 1, 1)->timestamp;
					$end   = Carbon::create(2025, 12, 31)->timestamp;

					$random_date = Carbon::createFromTimestamp(rand($start, $end));
					$formatted_date = $random_date->format($invalid_dates[$z]);
					
					$dates_to_check[] = [
						'format'	=>	$invalid_dates[$z],
						'date' 		=> 	$formatted_date
					];
					
					ClientsCustomField::factory()->create([
						'custom_field_type_id'		=>	$field_type->id,
						'company_id'				=>	1,
						'label'						=>	'client '.$z.''.$field_type->input_type,
						'default_value'				=>	$formatted_date,
						'order_on_add_edit_page'	=>	$order,
						'type_params'				=>	''
					]);

					$order++;

				}

			}

		
			
		}

		$params = http_build_query([
			'company_id'	=>	1
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $params, '/api/manage-clients/fetch-clients-custom-fields?');
		$response = $response->json();
		
		for($z = 0 ; $z < count($dates_to_check); $z++){

        	$this->assertEmpty($response[$z]['value']);

        }

	}

	public function test_if_it_fetches_clients_custom_fields_with_valid_datetime_formats(): void{

		
		$datetime_formats = [
			'Y-m-d h:i:s A',
			'd-M-Y h:i:s A',
			'd M Y h:i:s A',
			'Y-m-d h:i A',
			'Y-m-d H:i:s',
			'd-M-Y H:i:s',
			'd M Y H:i:s',
			'd-M-Y h:i A',
			'd M Y h:i A',
			'Y-m-d H:i',
			'd-M-Y H:i',
			'd M Y H:i'
		];
				

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

		$order = 1;
		ClientsCustomField::truncate();
		
		$dates_to_check = [];
		
		foreach($custom_field_types as $field_type){

			if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				
				for($z = 0 ; $z < count($datetime_formats) ; $z++){

					$start = Carbon::create(2010, 1, 1)->timestamp;
					$end   = Carbon::create(2025, 12, 31)->timestamp;

					$random_date = Carbon::createFromTimestamp(rand($start, $end));
					$formatted_date = $random_date->format($datetime_formats[$z]);
					
					$dates_to_check[] = [
						'format'	=>	$datetime_formats[$z],
						'date' 		=> 	$formatted_date
					];
					
					ClientsCustomField::factory()->create([
						'custom_field_type_id'		=>	$field_type->id,
						'company_id'				=>	1,
						'label'						=>	'client '.$z.''.$field_type->input_type,
						'default_value'				=>	$formatted_date,
						'order_on_add_edit_page'	=>	$order,
						'type_params'				=>	''
					]);

					$order++;

				}

			}

		
			
		}

		$params = http_build_query([
			'company_id'	=>	1
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $params, '/api/manage-clients/fetch-clients-custom-fields?');
		$response = $response->json();
		
		for($z = 0 ; $z < count($dates_to_check); $z++){

        	$carbon_date = DateTime::createFromFormat($dates_to_check[$z]['format'], $dates_to_check[$z]['date']);
            $temp_date = $carbon_date->format('Y-m-d H:i:s');
        	$this->assertEquals($temp_date, $response[$z]['value']);

        }

	}


	public function test_if_it_fetches_clients_custom_fields_with_invalid_datetime_formats(): void{

		$invalid_formats = [
			// Totally invalid / nonsense
			'abc',
			'123',
			'date-time',
			'DD-MM-YYYY',
			'YYYY/MM/DD',
			'something-random',

			// Valid PHP formats but not in your whitelist
			'Y-m-d',            // missing time
			'd-m-Y',            // uses d-m-Y, not allowed
			'd/m/Y H:i:s',      // slash separator
			'Y/m/d H:i:s',
			'Y.m.d H:i',
			'm/d/Y H:i:s',
			'j/n/Y H:i',
			'd.m.Y H:i:s',
			'y-m-d H:i:s',      // 2-digit year
			'd-M-y H:i',
			'j-M-y h:i A',
			'd M y H:i:s',
			'j M y h:i A',

			// Wrong tokens (not in PHP or not in list)
			'hh:mm:ss',
			'HH:MM:SS',
			'mm-dd-yyyy',
			'dd.mm.yyyy',
			'EEE, MMM d, YYYY',

			// Special formats (valid in PHP, but not in your array)
			'c',   // ISO 8601
			'r',   // RFC 2822
			'U',   // Unix timestamp
			'l, d-M-Y H:i:s', // weekday included

			// Time-only (not in your whitelist)
			'H:i:s',
			'h:i:s A',
			'H:i',
			'h:i A',

			// Edge / impossible formats
			'25:61:61',
			'2025-99-99',
			'0000-00-00 00:00:00'
		];

		

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

		$order = 1;
		ClientsCustomField::truncate();
		
		$dates_to_check = [];
		
		foreach($custom_field_types as $field_type){

			if($field_type->input_type === config('global.field_types')[7]){ /* datetime */
				
				for($z = 0 ; $z < count($invalid_formats) ; $z++){

					$start = Carbon::create(2010, 1, 1)->timestamp;
					$end   = Carbon::create(2025, 12, 31)->timestamp;

					$random_date = Carbon::createFromTimestamp(rand($start, $end));
					$formatted_date = $random_date->format($invalid_formats[$z]);
					
					$dates_to_check[] = [
						'format'	=>	$invalid_formats[$z],
						'date' 		=> 	$formatted_date
					];
					
					ClientsCustomField::factory()->create([
						'custom_field_type_id'		=>	$field_type->id,
						'company_id'				=>	1,
						'label'						=>	'client '.$z.''.$field_type->input_type,
						'default_value'				=>	$formatted_date,
						'order_on_add_edit_page'	=>	$order,
						'type_params'				=>	''
					]);

					$order++;

				}

			}

		
			
		}

		$params = http_build_query([
			'company_id'	=>	1
		]);

		$response = $this->getQuery($token, $refresh_token, $device, $params, '/api/manage-clients/fetch-clients-custom-fields?');
		$response = $response->json();
		
		for($z = 0 ; $z < count($dates_to_check); $z++){
			
        	$this->assertEmpty($response[$z]['value']);

        }

	}

}
