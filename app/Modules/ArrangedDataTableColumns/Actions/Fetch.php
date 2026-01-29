<?php

namespace App\Modules\ArrangedDataTableColumns\Actions;

use App\Helpers\General;
use App\Helpers\Sanitize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class Fetch{

	private function splitFields(array $json_fields, string $type) : array{

		$saved_labels_normal = [];
		$saved_ids_custom = [];

		for($z = 0 ; $z < count($json_fields) ; $z++){
			if($json_fields[$z]['type'] === 'normal'){
				$saved_labels_normal[] = $json_fields[$z]['label'];
			}else{
				$saved_ids_custom[] = $json_fields[$z][$type.'s_custom_fields_id'];
			}
		}

		return ['normal' => $saved_labels_normal, 'custom' => $saved_ids_custom];

	}

	private function parseSavedData(string $columns_json, string $type, string $custom_fields_model, int $company_id, array $table_columns) : array {

		$user_fields = [];

		$fields_json = json_decode($columns_json, true);

		$saved_labels_normal = [];
		$saved_ids_custom = [];
		
		$splitted = $this->splitFields($fields_json, $type);
		$saved_labels_normal = $splitted['normal'];
		$saved_ids_custom = $splitted['custom'];

		$general_custom_columns_ids = $custom_fields_model::where('company_id', '=', $company_id)->pluck('id')->toArray();
		
		foreach($fields_json as $field){

			if(in_array($field['label'], $table_columns) || in_array($field[$type.'s_custom_fields_id'], $general_custom_columns_ids)){
				$user_fields[] = $field;
			}

		}

		$counter = 1;
		foreach($table_columns as $temp_general_column){
			$to_push = [];
			if(!in_array($temp_general_column, $saved_labels_normal)){
				$to_push['id'] = $counter++;
				$to_push['label'] = $temp_general_column;
				$to_push['is_date'] = false;
				$to_push['text'] = General::NormalizeColumnName($temp_general_column);
				if($temp_general_column === 'created_at'){
					$to_push['text'] = 'Added on';
					$to_push['is_date'] = true;
				}
				$to_push['type'] = 'normal';
				$to_push['searchable'] = false;
				$to_push['show'] = false;
				
				$user_fields[] = $to_push;
			}
		}

		return [
			'user_fields'		=>		$user_fields,
			'saved_ids_custom'	=>		$saved_ids_custom,
			'counter'			=>		$counter
		];

	}

	private function getSavedColumnData(int $company_id, string $feature_name){

		$user_data = UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->first();

		if(!$user_data){
			$user_data = SettingsIndexColumn::where([['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->first();
		}

		return $user_data;

	}

	private function modifyParsedSavedData(array $user_fields, array $general_custom_columns, string $type) : array {

		for($z = 0 ; $z < count($user_fields) ; $z++){

			if($user_fields[$z]['type'] === 'custom'){

				for($x = 0 ; $x < count($general_custom_columns) ; $x++){

					if($general_custom_columns[$x]['id'] === $user_fields[$z][$type.'s_custom_fields_id']){

						$user_fields[$z]['text'] = General::NormalizeColumnName($general_custom_columns[$x]['label']);
						$user_fields[$z]['is_date'] = false;
						if($general_custom_columns[$x]['custom_field_type']['input_type'] === config('global.field_types')[5] || $general_custom_columns[$x]['custom_field_type']['input_type'] === config('global.field_types')[7]){
							$user_fields[$z]['is_date'] = true;
						}

					}

				}

			}

		}

		return $user_fields;


	}

	private function addNotSavedAndLaterAddedColumns(array $general_custom_columns, array $saved_ids_custom, int $counter, string $type) : array{

		$user_fields = [];

		foreach($general_custom_columns as $t_gen_custom_columns){
			
			$to_push = [];

			if($t_gen_custom_columns['custom_field_type']['input_type'] !== config('global.field_types')[9]){

				if(!in_array($t_gen_custom_columns['id'], $saved_ids_custom)){

					$to_push['id'] = $counter++;
					$to_push['label'] = '-';
					$to_push['text'] = General::NormalizeColumnName($t_gen_custom_columns['label']);
					$to_push['type'] = 'custom';
					$to_push[$type.'s_custom_fields_id'] = $t_gen_custom_columns['id'];
					$to_push['searchable'] = false;
					$to_push['show'] = false;
					$to_push['is_date'] = false;
					if($t_gen_custom_columns['custom_field_type']['input_type'] === config('global.field_types')[5] || $t_gen_custom_columns['custom_field_type']['input_type'] === config('global.field_types')[7]){
						$to_push['is_date'] = true;
					}

					$user_fields[] = $to_push;

				}
			}
		}

		return $user_fields;

	}

	private function nonUserDataColumns(array $columns, array $custom_columns, string $type) : array{

		$counter = 1;

		$merged = [];

		for($z = 0 ; $z < count($columns) ; $z++){

			$to_push = [];
			$to_push['id'] = $counter++;
			$to_push['label'] = $columns[$z];
			$to_push['text'] = General::NormalizeColumnName($columns[$z]);
			$to_push['type'] = 'normal';
			$to_push['is_date'] = false;
			$to_push['searchable'] = false;
			$to_push['show'] = false;

			if($columns[$z] === 'created_at'){
				$to_push['text'] = 'Added on';
				$to_push['is_date'] = true;
			}

			if($columns[$z] === 'first_name' || $columns[$z] === 'last_name' || $columns[$z] === 'email' || $columns[$z] === 'created_at'){
				$to_push['searchable'] = true;
				$to_push['show'] = true;
			}
			
			$merged[] = $to_push;

		}
		
		for($z = 0 ; $z < count($custom_columns) ; $z++){

			$to_push = [];
			if($custom_columns[$z]['custom_field_type']['input_type'] !== config('global.field_types')[9]){
				
				$to_push['id'] = $counter++;
				$to_push['label'] = '-';
				$to_push['text'] = ucfirst(strtolower($custom_columns[$z]['label']));
				$to_push['type'] = 'custom';
				$to_push[$type.'s_custom_fields_id'] = $custom_columns[$z]['id'];
				$to_push['searchable'] = false;
				$to_push['show'] = false;
				$to_push['is_date'] = false;
				
				if($custom_columns[$z]['custom_field_type']['input_type'] === config('global.field_types')[5] || $custom_columns[$z]['custom_field_type']['input_type'] === config('global.field_types')[7]){
					$to_push['is_date'] = true;
				}

				$merged[] = $to_push;
			}

		}

		return $merged;

	}

	public function fetchArrangedColumnsData(Request $request, string $feature_name, string $original_table, string $custom_fields_model, string $type) : array {
		
		$company_id = (int) Sanitize::input($request->input('company_id'));

		/* check if user has any data */
		$user_data = $this->getSavedColumnData($company_id, $feature_name);

		/* fetch all fields */
		$table_columns = Schema::getColumnListing($original_table);
		$table_columns = array_values(array_diff($table_columns, ['deleted_at', 'updated_at']));
		
		$general_custom_columns = $custom_fields_model::where('company_id', '=', $company_id)->whereHas('customFieldType')->with('customFieldType')->get()->toArray();

		$user_fields = [];

		/* handle userdata here */
		if($user_data){

			$temp = $this->parseSavedData($user_data->columns_json, $type, $custom_fields_model, $company_id, $table_columns);
			$user_fields = $temp['user_fields'];
			$saved_ids_custom = $temp['saved_ids_custom'];
			$counter = $temp['counter'];

			$user_fields = $this->modifyParsedSavedData($user_fields, $general_custom_columns, $type);

			$user_fields = array_merge($user_fields, $this->addNotSavedAndLaterAddedColumns($general_custom_columns, $saved_ids_custom, $counter, $type));

			return $user_fields;
			
		}else{
			return $this->nonUserDataColumns($table_columns, $general_custom_columns, $type);
		}
		


	}

}