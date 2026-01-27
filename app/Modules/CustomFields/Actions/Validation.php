<?php

namespace App\Modules\CustomFields\Actions;

use App\Helpers\Sanitize;
use App\Modules\CustomFields\Exceptions\InvalidCustomFieldsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Validation class
 */
class Validation{
	
	/**
	 * validateCustomFields function
	 *
	 * @param Request $request
	 * @param string $model
	 * @param string $validity
	 * @param integer $tab
	 * @return boolean
	 */
	public function validateCustomFields(Request $request, string $model, string $validity, int $tab = 3) : bool {

		$response = ['message' => 'Please fill in required fields', 'validity' => $validity, 'tab_switch' => $tab];

		if(!$request->has('custom_fields')){
			throw new InvalidCustomFieldsException('no custom fields', $validity, config('global.error_code'), $tab);
		}

		$company_id = Sanitize::input($request->input('company_id'));

		$db_custom_fields = $model::where('company_id', '=', $company_id)->whereHas('customFieldType')->get();
		
		if($db_custom_fields->isEmpty()){
			return false;
		}
		
		$validation_rules = [
			'custom_fields'	 =>	'required|array'
		];
		
		$custom_fields_validation_1 = Validator::make($request->all(), $validation_rules);
		if($custom_fields_validation_1->fails()){
			throw new InvalidCustomFieldsException($response['message'], $validity, config('global.error_code'), $tab);
		}
		
		$custom_fields_submitted = $request->input('custom_fields');

		$validation_rules = [];

		$required_count = 0;
		
		$found_and_valid = 0;
		/* generate validation rules dynamically */
		foreach($db_custom_fields as $field){

			if($field->required == 1){

				$required_count++;

				for($z = 0 ; $z < count($custom_fields_submitted) ; $z++){

					if($custom_fields_submitted[$z]['id'] === $field->id){

						$found_and_valid++;

						if($field->customFieldType->input_type === config('global.field_types')[0] || $field->customFieldType->input_type === config('global.field_types')[1] || $field->customFieldType->input_type === config('global.field_types')[3] || $field->customFieldType->input_type === config('global.field_types')[9]){
							
							$validation_rules['custom_fields.'.$z.'.value'] = 'required';

						}else{

							if($field->customFieldType->input_type === config('global.field_types')[2]){ //email
								
								$validation_rules['custom_fields.'.$z.'.value'] = 'required|email';

							}else if($field->customFieldType->input_type === config('global.field_types')[4]){ //number

								$validation_rules['custom_fields.'.$z.'.value'] = 'required|numeric';

							}else if($field->customFieldType->input_type === config('global.field_types')[5] || $field->customFieldType->input_type === config('global.field_types')[7]){ //date and datetime

								$validation_rules['custom_fields.'.$z.'.value'] = 'required|date';

							}else if($field->customFieldType->input_type === config('global.field_types')[6]){ //time

								$validation_rules['custom_fields.'.$z.'.value'] = 'required|array';
								$validation_rules['custom_fields.'.$z.'.value.hours'] = 'required|integer|between:0,23';
								$validation_rules['custom_fields.'.$z.'.value.minutes'] = 'required|integer|between:0,59';
								$validation_rules['custom_fields.'.$z.'.value.seconds'] = 'required|integer|between:0,59';

							}else if($field->customFieldType->input_type === config('global.field_types')[8]){ //telephone

								$validation_rules['custom_fields.'.$z.'.value'] = 'required|regex:/^\+?\d+$/';

							}

						}
						

					}

				}

				if(count($custom_fields_submitted) !== count($db_custom_fields) && $found_and_valid === $required_count){
					throw new InvalidCustomFieldsException($response['message'], $validity, config('global.error_code'), $tab);
				}

			}

		}

		$custom_fields_validation_2 = Validator::make($request->all(), $validation_rules);
		if($custom_fields_validation_2->fails()){
			throw new InvalidCustomFieldsException($response['message'], $validity, config('global.error_code'), $tab);
		}

		return true;

	}

}