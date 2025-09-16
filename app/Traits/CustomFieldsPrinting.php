<?php

	namespace App\Traits;

	use App\Helpers\General;
	use Illuminate\Database\Eloquent\Collection;

	trait CustomFieldsPrinting{

		private function getDateFormats():array{
			return [
				'Y-m-d',        // 2025-08-19
				'd-M-Y',        // 01-Jan-2025  
				'j-M-Y',        // 1-Jan-2025
				'd M Y',        // 21 Jan 2025 (with leading zeros)
				'j M Y',        // 1 Jan 2025 (without leading zeros)
			];
		}

		private function getDateTimeFormats():array{
			return [
				'Y-m-d h:i:s A',    // 2025-01-20 05:04:25 PM
				'd-M-Y h:i:s A',    // 20-Jan-2025 05:04:25 PM  
				'd M Y h:i:s A',    // 20 Jan 2025 05:04:25 PM
				'Y-m-d h:i A',      // 2025-01-20 05:04 PM
				'Y-m-d H:i:s',      // 2025-01-20 17:04:25
				'd-M-Y H:i:s',      // 20-Jan-2025 17:04:25
				'd M Y H:i:s',      // 20 Jan 2025 17:04:25
				'd-M-Y h:i A',      // 20-Jan-2025 05:04 PM
				'd M Y h:i A',      // 20 Jan 2025 05:04 PM
				'Y-m-d H:i',        // 2025-01-20 17:04
				'd-M-Y H:i',        // 20-Jan-2025 17:04
				'd M Y H:i'       	// 20 Jan 2025 17:04
			];
		}

		private function getFullWidthTypes(){
			return [
				config('global.field_types')[1],
				config('global.field_types')[9]
			];
		}

		private function processCurrentRowNRows(Collection $fields):array{

			$full_width_types = $this->getFullWidthTypes();
			$current_row = [];
			$rows = [];

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

			return [
				'rows' 			=> $rows,
				'current_row' 	=> $current_row,
			];

		}

		private function handleFieldParams(string $arg_params, array $temp_params):array{

			if(isset($arg_params) && $arg_params !== ''){
				$temp = array_map('trim', explode(',', $arg_params));
				$temp_params = $temp;
				$params = [];
				for($z = 0 ; $z < count($temp) ; $z++){
					$params[] = [
						'value'	=>	$temp[$z],
						'text'	=>	$temp[$z]
					];
					
				}
				$arg_params = $params;
				$params = null;
			}else{
				$arg_params = [];
			}
			return [
				'temp_params' 	=> 	$temp_params,
				'arg_params'	=>	$arg_params
			];
			
		}

		public function adjustRowsPrinting(Collection $fields):\Illuminate\Support\Collection{

			$date_formats = $this->getDateFormats();
			$datetime_formats = $this->getDateTimeFormats();

			$pca = $this->processCurrentRowNRows($fields);
			$rows = $pca['rows'];
			$current_row = $pca['current_row'];
			$pca = null;

			if(!empty($current_row)){
				$rows[] = $current_row;
			}
			$index = 0;
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
					$temp_params = [];
					$field->span = $span;

					$field->value = trim($field->default_value);
					$field->error = '';

					$t_params = $this->handleFieldParams($field->type_params, $temp_params);
					$temp_params = $t_params['temp_params'];
					$field->type_params = $t_params['arg_params'];

					$required = false;
					if($field->required === 1){
						$required = true;
					}
					
					$field->required = $required;

					if($field->customFieldType->input_type === config('global.field_types')[2]){ /* email */

						if(!filter_var($field->default_value, FILTER_VALIDATE_EMAIL)){
							$field->value = '';
						}

					}

					if($field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
						
						if(!in_array($field->default_value, $temp_params)){
							$field->value = '';
						}
						
					}

					if($field->customFieldType->input_type === config('global.field_types')[4]){
						if(filter_var($field->default_value, FILTER_VALIDATE_INT) === false){
							$field->value = '';
						}
					}

					if($field->customFieldType->input_type === config('global.field_types')[5]){ //date only
						
						$default_value = trim($field->default_value);
						$parsed = false;
						
						foreach($date_formats as $format){
							if((\DateTime::createFromFormat($format, $default_value) !== false)){
								
								$default_value = \DateTime::createFromFormat($format, $default_value)->format('Y-m-d');
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
							$field->value = General::convertToStandardTime($default_value);
						}
						
						$field->default_value = '';

					}

					if($field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
						
						$default_value = General::fixMonthNames(trim($field->default_value));
						$parsed = null;

						foreach($datetime_formats as $format){

							$date_obj = \DateTime::createFromFormat($format, $default_value);
							
							if($date_obj !== false && $date_obj->format($format) === $default_value){
								
								$default_value = \DateTime::createFromFormat($format, $default_value)->format('Y-m-d H:i:s');
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

					if($field->customFieldType->input_type === config('global.field_types')[8]){
						
						$default_value = trim($field->default_value);

						$field->default_value = '';
						$field->value = '';
						if(General::isValidPhoneNumber($default_value)){
							$field->value = $default_value;
						}

					}


					if($field->customFieldType->input_type === config('global.field_types')[9]){

						if(!in_array($field->default_value, $temp_params)){
							$field->value = [];
						}else{
							$default_value = trim($field->default_value);
							$field->value = [$default_value];
						}
						$field->default_value = '';
						
					}

					$field->ref = "cf_client_".$index."_".General::onlyLettersAndNumbers($field->label);
					
					$index++;
					
					
				}

			}
			
			return collect($rows)->flatten();

		}

	}
	