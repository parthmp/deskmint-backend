<?php

namespace Tests\Traits;

use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomFieldType;
use App\Models\Industry;

trait CustomFields{

	protected function setCustomFieldTypes() : void{

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

	protected function addAllCustomFields(){
		
	}
}