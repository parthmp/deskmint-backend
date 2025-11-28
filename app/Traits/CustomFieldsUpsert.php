<?php

namespace App\Traits;

use App\Helpers\General;
use App\Helpers\Sanitize;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait CustomFieldsUpsert{

	private function getDBCustomFieldsForStore(string $custom_fields_model, int $company_id){
		return $custom_fields_model::where('company_id', '=', $company_id)->whereHas('customFieldType')->get();
	}

	private function getDBCustomFields(int $db_id, string $custom_fields_model, int $company_id, bool $add, string $type) : Collection{
		
		// if(!$add){
			
		// 	$db_custom_fields = $custom_fields_model::where('company_id', $company_id)->whereHas('customFieldType')->whereHas('customFieldValue', function($query) use ($db_id, $type){
		// 		$query->where($type.'_id', $db_id);
		// 	})->with(['customFieldValue' => function($query) use ($db_id, $type) {
		// 		$query->where($type.'_id', $db_id);
		// 	}])->get();

		// 	if($db_custom_fields->count() === 0){
		// 		$db_custom_fields = $this->getDBCustomFieldsForStore($custom_fields_model, $company_id);
		// 	}

		// }else{
		// 	$db_custom_fields = $this->getDBCustomFieldsForStore($custom_fields_model, $company_id);
		// }

		$db_custom_fields = $custom_fields_model::where('company_id', $company_id)->whereHas('customFieldType')->with(['customFieldValue' => function($query) use ($db_id, $type) {
        	$query->where($type . '_id', $db_id);
    	}])->get();


		return $db_custom_fields;

	}

	private function generalFlatValue(mixed $value, string $input_type) : array{

		$value = trim($value);
		$flat_value = $value;
		if($input_type === config('global.field_types')[4] && trim($value) == ''){
			$flat_value = null;
		}
		return ['flat_value' => $flat_value, 'value' => $value];
	}

	private function dateNdatetimeFlatValue(mixed $value) : array{

		$r_value = '';
		$r_flat_value = null;

		if($value !== null || $value !== ''){

			$datetime_string = trim($value);
			
			if(General::isValidISODateTime($datetime_string)){
				
				$r_value = $datetime_string;
				$r_flat_value = Carbon::parse($datetime_string)->format('Y-m-d H:i:s'); //for date and time
				
			}else{
				$r_value = '';
				$r_flat_value = null;
			}
			

		}
		
		return ['flat_value' => $r_flat_value, 'value' => $r_value];

	}

	private function timeFlatValue(int $required, mixed $value){

		if($required === 1){
			$value = General::jsonTimeToAmPm(json_encode($value));
			$flat_value = $value;
		}else{
			if($value !== null){
				$value = General::jsonTimeToAmPm(json_encode($value));
				$flat_value = $value;
			}else{
				$value = json_encode('');
				$flat_value = '';
			}
		}

		return ['flat_value' => $flat_value, 'value' => $value];
	}

	protected function upsertCustomFieldValues(Request $request, int $db_id, string $custom_fields_model, string $custom_fields_value_model, string $flat_table, string $type = 'client', bool $add = true){
		
		$company_id = Sanitize::input($request->input('company_id'));
		$db_custom_fields = $this->getDBCustomFields($db_id, $custom_fields_model, (int) $company_id, $add, $type);
		
		$upsert = [];
		$insert_flat = [];
		$insert_flat[$type.'_id'] = $db_id;

		foreach($db_custom_fields as $field){

			$custom_fields_submitted = $request->input('custom_fields');

			$value = '';
			$flat_value = '';

			for($z = 0 ; $z < count($custom_fields_submitted) ; $z++){

				if($custom_fields_submitted[$z]['id'] == $field->id){

					if(!$add && isset($field->customFieldValue->id)){
						$field->value_id = $field->customFieldValue->id;
					}

					if($field->customFieldType->input_type === config('global.field_types')[0] || $field->customFieldType->input_type === config('global.field_types')[1] || $field->customFieldType->input_type === config('global.field_types')[3] || $field->customFieldType->input_type === config('global.field_types')[2] || $field->customFieldType->input_type === config('global.field_types')[4] || $field->customFieldType->input_type === config('global.field_types')[8]){ //text, textarea, select, email, number, telephone
						
						if(!isset($custom_fields_submitted[$z]['value']) || $custom_fields_submitted[$z]['value'] === null){
							$custom_fields_submitted[$z]['value'] = '';
						}

						$temp_values = $this->generalFlatValue($custom_fields_submitted[$z]['value'], $field->customFieldType->input_type);
						$flat_value = $temp_values['flat_value'];
						$value = $temp_values['value'];

					}else{

						if($field->customFieldType->input_type === config('global.field_types')[5] || $field->customFieldType->input_type === config('global.field_types')[7]){ //date and datetime

							$temp_values = $this->dateNdatetimeFlatValue($custom_fields_submitted[$z]['value']);
							$flat_value = $temp_values['flat_value'];
							$value = $temp_values['value'];
							

						}else if($field->customFieldType->input_type === config('global.field_types')[6]){ //time

							$temp_values = $this->timeFlatValue($field->required, $custom_fields_submitted[$z]['value']);
							$flat_value = $temp_values['flat_value'];
							$value = $temp_values['value'];

						}else if($field->customFieldType->input_type === config('global.field_types')[9]){ //multiselect
							$value = json_encode('');
							if($custom_fields_submitted[$z]['value'] !== null){
								$value = json_encode($custom_fields_submitted[$z]['value']);
								$flat_value = $value;
							}
						}

					}

					if($custom_fields_submitted[$z]['id'] == $field->id){
						$custom_column_name = General::replaceWithUnderscores($field->label);
						$insert_flat[$custom_column_name] = $flat_value;
					}

				}
			}
			$temp_upsert = [];
			if(!$add && isset($field->value_id)){
				$temp_upsert['id'] = $field->value_id;
			}else{
				$temp_upsert['id'] = null;
			}
			
			$temp_upsert[$type.'_id'] = $db_id;
			$temp_upsert[$type.'s_custom_field_id'] = $field->id;
			$temp_upsert['field_value'] = $value;

			$upsert[] = $temp_upsert;
		}

		if(!empty($upsert)){
			$custom_fields_value_model::upsert($upsert, ['id'], [$type.'_id', $type.'s_custom_field_id', 'field_value']);
		}

		$insert_flat['created_at'] = now();
		$insert_flat['updated_at'] = now();
		if(!$add){
			DB::table($flat_table)->where($type.'_id', '=', $db_id)->delete();
		}
		DB::table($flat_table)->insert($insert_flat);
		
	}

}