<?php

namespace App\Modules\CustomFields\Actions;

use App\Helpers\General;
use DateTime;
use Illuminate\Database\Eloquent\Collection;

class Printing{

	/**
	 * getDateFormats function
	 *
	 * @return array
	 */
	private function getDateFormats() : array {
		return [
			'Y-m-d',        // 2025-08-19
			'd-M-Y',        // 01-Jan-2025  
			'j-M-Y',        // 1-Jan-2025
			'd M Y',        // 21 Jan 2025 (with leading zeros)
			'j M Y',        // 1 Jan 2025 (without leading zeros)
		];
	}

	/**
	 * getDateTimeFormats function
	 *
	 * @return array
	 */
	private function getDateTimeFormats() : array {
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

	/**
	 * getFullWidthTypes function
	 *
	 * @return array
	 */
	private function getFullWidthTypes() : array {
		return [
			config('global.field_types')[1],
			config('global.field_types')[9]
		];
	}

	/**
	 * processCurrentRowNRows function
	 *
	 * @param Collection $fields
	 * @return array
	 */
	private function processCurrentRowNRows(Collection $fields) : array{

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

	/**
	 * handleFieldParams function
	 *
	 * @param string $arg_params
	 * @param array $temp_params
	 * @return array
	 */
	private function handleFieldParams(string $arg_params, array $temp_params) : array {

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

	/**
	 * emailField function
	 *
	 * @param string $value
	 * @return string
	 */
	private function emailField(string $value) : string {

		$return_value = $value;

		if(!filter_var($return_value, FILTER_VALIDATE_EMAIL)){
			$return_value = '';
		}

		return $return_value;

	}

	/**
	 * selectField function
	 *
	 * @param string $value
	 * @param array $params
	 * @return string
	 */
	private function selectField(string $value, array $params) : string {
		$return_value = $value;
		if(!in_array($return_value, $params)){
			$return_value = '';
		}
		return $return_value;
	}

	/**
	 * numberField function
	 *
	 * @param string $value
	 * @return string
	 */
	private function numberField(string $value) : string {
		$return_value = $value;
		if(filter_var($return_value, FILTER_VALIDATE_INT) === false){
			$return_value = '';
		}
		return $return_value;
	}

	/**
	 * dateField function
	 *
	 * @param string $value
	 * @param array $date_formats
	 * @return string
	 */
	private function dateField(string $value, array $date_formats) : string{

		$default_value = trim($value);
		$parsed = false;
		
		foreach($date_formats as $format){
			if((DateTime::createFromFormat($format, $default_value) !== false)){
				
				$default_value = DateTime::createFromFormat($format, $default_value)->format('Y-m-d');
				$parsed = true;
				break;
				
			}
		}
		
		if(!$parsed){
			$default_value = '';
		}else{
			$default_value = (new DateTime())->createFromFormat('Y-m-d', $default_value)->format('Y-m-d\TH:i:s.000000\Z');
		}

		return $default_value;

	}

	/**
	 * timeField function
	 *
	 * @param string $value
	 * @return string
	 */
	private function timeField(string $value) : string{

		$return_value = '';
		$default_value = trim($value);
		if(General::isValidTime($default_value)){
			$return_value = General::convertToStandardTime($default_value);
		}
		return $return_value;
	}

	/**
	 * datetimeField function
	 *
	 * @param string $value
	 * @param array $datetime_formats
	 * @return string
	 */
	private function datetimeField(string $value, array $datetime_formats) : string {

		$return_value = '';

		$default_value = General::fixMonthNames(trim($value));
		$parsed = null;

		foreach($datetime_formats as $format){

			$date_obj = DateTime::createFromFormat($format, $default_value);
			
			if($date_obj !== false && $date_obj->format($format) === $default_value){
				
				$default_value = DateTime::createFromFormat($format, $default_value)->format('Y-m-d H:i:s');
				$parsed = true;
				break;
			}
			
		}
		
		if($parsed){
			
			$default_value = (new DateTime())->createFromFormat('Y-m-d H:i:s', $default_value)->format('Y-m-d\TH:i:s.000000\Z');
			$return_value = $default_value;

		}

		return $return_value;

	}

	/**
	 * phoneField function
	 *
	 * @param string $value
	 * @return string
	 */
	private function phoneField(string $value) : string {
		
		$return_value = '';
		$default_value = trim($value);
		if(General::isValidPhoneNumber($default_value)){
			$return_value = $default_value;
		}
		return $return_value;
	}

	/**
	 * multiselectField function
	 *
	 * @param string $value
	 * @param array $params
	 * @return array
	 */
	private function multiselectField(string $value, array $params) : array {

		$return_value = [];
		if(in_array($value, $params)){
			$default_value = trim($value);
			$return_value = [$default_value];
		}
		return $return_value;
	}

	/**
	 * adjustRowsPrinting function
	 *
	 * @param Collection $fields
	 * @return \Illuminate\Support\Collection
	 */
	public function adjustRowsPrinting(Collection $fields) : \Illuminate\Support\Collection {

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
					$field->value = $this->emailField($field->default_value);
				}

				if($field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
					$field->value = $this->selectField($field->default_value, $temp_params);
				}

				if($field->customFieldType->input_type === config('global.field_types')[4]){
					$field->value = $this->numberField($field->default_value);
				}

				if($field->customFieldType->input_type === config('global.field_types')[5]){ //date only
					$field->value = $this->dateField($field->default_value, $date_formats);
				}

				if($field->customFieldType->input_type === config('global.field_types')[6]){
					$field->value = $this->timeField($field->default_value);
				}

				if($field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
					$field->value = $this->datetimeField($field->default_value, $datetime_formats);
				}

				if($field->customFieldType->input_type === config('global.field_types')[8]){
					$field->value = $this->phoneField($field->default_value);
				}


				if($field->customFieldType->input_type === config('global.field_types')[9]){
					$field->value = $this->multiselectField($field->default_value, $temp_params);
				}

				$field->default_value = '';

				$field->ref = "cf_client_".$index."_".General::onlyLettersAndNumbers($field->label);
				
				$index++;
				
				
			}

		}
		
		return collect($rows)->flatten();

	}

}