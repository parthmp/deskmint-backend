<?php

namespace Tests\Traits;

use App\Models\ClientsCustomField;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomFieldType;
use App\Models\Industry;

trait CustomFields{

	protected function setCustomFieldTypes() : void{
		CustomFieldType::truncate();
		/* set custom field types */
		foreach(config('global.field_types') as $field){

			CustomFieldType::factory()->create([
				'input_type'	=>	$field,
				'input_name'	=>	'client '.$field
			]);
			
		}

	}

	protected function clientStoreData(Currency $currency, Country $country, Industry $industry, int $company_id, array $custom_fields_post = []):array{
		
		return [
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
	}

	protected function setCustomFields(){

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

		return [
			'fields'	=>	$custom_fields_post,
			'types'		=>	$custom_fields_types
		];

	}

	protected function addAllCustomFields(int $company_id, $headers):array{
		$this->setCustomFieldTypes();
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
			], $headers);
			
			$order++;
		}

		return [
			'order'	=>	$order
		];

	}
}