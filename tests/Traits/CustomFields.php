<?php

namespace Tests\Traits;

use App\Models\CustomFieldType;

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
}