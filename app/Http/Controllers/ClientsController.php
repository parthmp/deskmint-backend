<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;

class ClientsController extends Controller{

	public function fetchClientsCustomFields(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();

		return $this->adjustRowsPrinting($fields);

	}

	private function adjustRowsPrinting($fields){ 

		$full_width_types = [
			config('global.field_types')[1],
			config('global.field_types')[9]
		];

		$date_formats = [
			'Y-m-d',        // 2025-08-19
			'd/m/Y',        // 19/08/2025
			'm/d/Y',        // 08/19/2025
			'd-M-Y',        // 01-Jan-2025
			'j-M-Y',        // 1-Jan-2025
		];

		$datetime_formats = [
			// Date + 24h time
			'Y-m-d H:i',
			'Y-m-d H:i:s',
			'd/m/Y H:i',
			'd/m/Y H:i:s',
			'm/d/Y H:i',
			'm/d/Y H:i:s',
			'd-M-Y H:i',
			'd-M-Y H:i:s',
			'j-M-Y H:i',
			'j-M-Y H:i:s',

			// Date + 12h time with AM/PM
			'Y-m-d h:i A',
			'Y-m-d h:i:s A',
			'd/m/Y h:i A',
			'd/m/Y h:i:s A',
			'm/d/Y h:i A',
			'm/d/Y h:i:s A',
			'd-M-Y h:i A',
			'd-M-Y h:i:s A',
			'j-M-Y h:i A',
			'j-M-Y h:i:s A',
		];

		$rows = [];
		$current_row = [];

		foreach($fields as $field){

			$current_type = $field->customFieldType->input_type;
			

			if(in_array($current_type, $full_width_types)){
				
				if(!empty($current_row)){
					$rows[] = $current_row;
					$current_row = [];
				}
				
				$rows[] = [$field];

			}else{

				$current_row[] = $field;
				if(count($current_row) == 3){
					$rows[] = $current_row;
					$current_row = [];
				}
			
			}

		}

		if(!empty($current_row)){
			$rows[] = $current_row;
		}

		foreach($rows as $row){

			$count = count($row);
			$span = 12;
			
			if($count === 2){
				$span = 6;
			}
			
			if($count === 3){
				$span = 4;
			}
			
			foreach($row as $field){

				$field->span = $span;

				$field->value = $field->default_value;
				$field->error = '';
		
				if(isset($field->type_params) && $field->type_params !== ''){
					$temp = array_map('trim', explode(',', $field->type_params));
					$params = [];
					for($z = 0 ; $z < count($temp) ; $z++){
						$params[] = [
							'value'	=>	$temp[$z],
							'text'	=>	$temp[$z]
						];
					}
					$field->type_params = $params;
					$params = null;
				}else{
					$field->type_params = [];
				}

				$required = false;
				if($field->required === 1){
					$required = true;
				}
				
				$field->required = $required;

				if($field->customFieldType->input_type === config('global.field_types')[4]){
					if(filter_var($field->default_value, FILTER_VALIDATE_INT) === false){
						$field->value = '';
					}
				}

				if($field->customFieldType->input_type === config('global.field_types')[5]){
					
					$default_value = trim($field->default_value);
					$parsed = null;

					foreach ($date_formats as $format) {
						if((\DateTime::createFromFormat($format, $default_value) !== false)){
							$parsed = true;
							break;
						}
					}
					
					if($parsed){
						$field->value = $default_value;
					}else{
						$field->value = '';
					}

					$field->default_value = '';
					
				}

				if($field->customFieldType->input_type === config('global.field_types')[6]){

					$default_value = trim($field->default_value);

					$field->value = '';
					if(General::isValidTime($default_value)){
						$field->value = $default_value;
					}
					
					$field->default_value = '';

				}

				if($field->customFieldType->input_type === config('global.field_types')[7]){
					
					$default_value = trim($field->default_value);
					$parsed = null;

					foreach ($datetime_formats as $format) {
						if((\DateTime::createFromFormat($format, $default_value) !== false)){
							$parsed = true;
							break;
						}
					}
					
					if($parsed){
						$field->value = $default_value;
					}else{
						$field->value = '';
					}

					$field->default_value = '';
					
				}

				if($field->customFieldType->input_type === config('global.field_types')[9]){
					
					$default_value = trim($field->default_value);
					$field->value = [$default_value];

					
					$field->default_value = '';
					
				}

				

				
				
			}

		}
		
		return collect($rows)->flatten();

	}
	

}
