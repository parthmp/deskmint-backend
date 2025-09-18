<?php

namespace App\Traits;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\SettingsIndexColumn;
use App\Models\UserIndexColumn;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

trait ArrangedColumns{

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

	protected function fetchArrangedColumnsData(Request $request, string $feature_name, string $original_table, string $custom_fields_model, string $type) : array{
		
		$company_id = Sanitize::input($request->input('company_id'));

		/* check if user has any data */
		$user_data = UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->first();

		if(!$user_data){
			$user_data = SettingsIndexColumn::where([['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->first();
		}

		/* fetch all fields */
		$table_columns = Schema::getColumnListing($original_table);
		$table_columns = array_values(array_diff($table_columns, ['deleted_at', 'updated_at']));
		
		$clients_custom_columns = $custom_fields_model::where('company_id', '=', $company_id)->whereHas('customFieldType')->with('customFieldType')->get()->toArray();

		$user_fields = [];

		/* handle userdata here */
		if($user_data){
			
			$fields_json = json_decode($user_data->columns_json, true);

			$saved_labels_normal = [];
			$saved_ids_custom = [];
			
			$splitted = $this->splitFields($fields_json, $type);
			$saved_labels_normal = $splitted['normal'];
			$saved_ids_custom = $splitted['custom'];

			$table_columns = Schema::getColumnListing($original_table);
			$table_columns = array_values(array_diff($table_columns, ['deleted_at', 'updated_at']));
			
			$clients_custom_columns_ids = $custom_fields_model::where('company_id', '=', $company_id)->pluck('id')->toArray();
			
			foreach($fields_json as $field){

				if(in_array($field['label'], $table_columns) || in_array($field[$type.'s_custom_fields_id'], $clients_custom_columns_ids)){
					$user_fields[] = $field;
				}

			}

			$counter = 1;
			foreach($table_columns as $temp_client_column){
				$to_push = [];
				if(!in_array($temp_client_column, $saved_labels_normal)){
					$to_push['id'] = $counter++;
					$to_push['label'] = $temp_client_column;
					$to_push['is_date'] = false;
					$to_push['text'] = General::NormalizeColumnName($temp_client_column);
					if($temp_client_column === 'created_at'){
						$to_push['text'] = 'Added on';
						$to_push['is_date'] = true;
					}
					$to_push['type'] = 'normal';
					$to_push['searchable'] = false;
					$to_push['show'] = false;
					
					$user_fields[] = $to_push;
				}
			}

			/* modify text to show */
			for($z = 0 ; $z < count($user_fields) ; $z++){

				if($user_fields[$z]['type'] === 'custom'){

					for($x = 0 ; $x < count($clients_custom_columns) ; $x++){

						if($clients_custom_columns[$x]['id'] === $user_fields[$z][$type.'s_custom_fields_id']){

							$user_fields[$z]['text'] = General::NormalizeColumnName($clients_custom_columns[$x]['label']);
							$user_fields[$z]['is_date'] = false;
							if($clients_custom_columns[$x]['custom_field_type']['input_type'] === config('global.field_types')[5] || $clients_custom_columns[$x]['custom_field_type']['input_type'] === config('global.field_types')[7]){
								$user_fields[$z]['is_date'] = true;
							}

						}

					}

				}

			}

			/*  */

			foreach($clients_custom_columns as $t_clients_custom_columns){
				$to_push = [];
				if($t_clients_custom_columns['custom_field_type']['input_type'] !== config('global.field_types')[9]){
					if(!in_array($t_clients_custom_columns['id'], $saved_ids_custom)){
						$to_push['id'] = $counter++;
						$to_push['label'] = '-';
						$to_push['text'] = General::NormalizeColumnName($t_clients_custom_columns['label']);
						$to_push['type'] = 'custom';
						$to_push[$type.'s_custom_fields_id'] = $t_clients_custom_columns['id'];
						$to_push['searchable'] = false;
						$to_push['show'] = false;
						$to_push['is_date'] = false;
						if($t_clients_custom_columns['custom_field_type']['input_type'] === config('global.field_types')[5] || $t_clients_custom_columns['custom_field_type']['input_type'] === config('global.field_types')[7]){
							$to_push['is_date'] = true;
						}
						$user_fields[] = $to_push;
					}
				}
			}

			return $user_fields;
			
		}else{
			return $this->nonUserDataColumns($table_columns, $clients_custom_columns, $type);
		}
		


	}

	protected function saveArrangedColumnsData(Request $request, string $custom_fields_model, string $feature_name, string $original_table, string $type){
		
		$validation_rules = [
			'columns'	 =>	'required|array'
		];

		$v = Validator::make($request->all(), $validation_rules);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$columns = $request->input('columns');
		$company_id = Sanitize::input($request->input('company_id'));

		if(empty($columns)){
			UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->delete();
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}

		$clients_columns = Schema::getColumnListing($original_table);
		$clients_columns = array_values(array_diff($clients_columns, ['deleted_at', 'updated_at']));
		
		$clients_custom_column_ids = $custom_fields_model::where('company_id', '=', $company_id)->pluck('id')->toArray();

		for($z = 0 ; $z < count($columns) ; $z++){
			if(!isset($columns[$z][$type.'s_custom_fields_id'])){
				$columns[$z][$type.'s_custom_fields_id'] = '-';
			}
			if(!in_array($columns[$z]['label'], $clients_columns) && !in_array($columns[$z][$type.'s_custom_fields_id'], $clients_custom_column_ids)){
				return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}

		}
		
		try{

			$columns = json_encode($columns);
			
			UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->delete();
			
			$user_index_col = new UserIndexColumn();
			$user_index_col->user_id = Auth::user()->id;
			$user_index_col->company_id = $company_id;
			$user_index_col->feature_name = $feature_name;
			$user_index_col->columns_json = $columns;
			$user_index_col->save();
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}
	}

}