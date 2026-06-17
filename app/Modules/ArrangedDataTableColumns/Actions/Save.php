<?php

namespace App\Modules\ArrangedDataTableColumns\Actions;

use App\Helpers\Sanitize;
use App\Modules\ArrangedDataTableColumns\DatabaseOperations\DatabaseOperations;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Save class
 */
class Save{

	/**
	 * __construct function
	 *
	 * @param Validation $validation
	 * @param DatabaseOperations $database_operations
	 */
	public function __construct(private Validation $validation, private DatabaseOperations $database_operations){}

	/**
	 * saveArrangedColumnsData function
	 *
	 * @param Request $request
	 * @param string $custom_fields_model
	 * @param string $feature_name
	 * @param string $original_table
	 * @param string $type
	 * @param array $additional_fields
	 * @param array $date_fields
	 * @return boolean
	 */
	public function saveArrangedColumnsData(Request $request, string $custom_fields_model, string $feature_name, string $original_table, string $type, array $additional_fields = [], $date_fields = []) : bool {
		
		$validated = $this->validation->validatePostedColumns($request);
		if(!$validated){
			throw new InvalidDataProvidedException("Invalid data provided", "invalid_request", config('global.error_code'));
		}

		$columns = $request->input('columns');
		$company_id = Sanitize::input($request->input('company_id'));

		if(empty($columns)){
			$this->database_operations->deleteUserIndexColumn($company_id, $feature_name);
			return true;
		}

		$general_columns = Schema::getColumnListing($original_table);
		
		$general_columns = array_values(array_diff($general_columns, ['deleted_at', 'updated_at']));
		
		$general_custom_column_ids = $custom_fields_model::where('company_id', '=', $company_id)->pluck('id')->toArray();

		$additional_fields_names = [];

		if(!empty($additional_fields)){
			$additional_fields_names = array_column($additional_fields, 'label');
		}

		for($z = 0 ; $z < count($columns) ; $z++){
			if(!isset($columns[$z][$type.'s_custom_fields_id'])){
				$columns[$z][$type.'s_custom_fields_id'] = '-';
			}
			if(!in_array($columns[$z]['label'], $general_columns) && !in_array($columns[$z][$type.'s_custom_fields_id'], $general_custom_column_ids) && !in_array($columns[$z]['label'], $additional_fields_names)){
				throw new InvalidDataProvidedException("Invalid data provided", "invalid_request", config('global.error_code'));
			}

		}
		
		try{

			$columns = json_encode($columns);
			
			$this->database_operations->deleteUserIndexColumn($company_id, $feature_name);

			return $this->database_operations->createNewIndexColumn([
				'company_id'		=>		$company_id,
				'feature_name'		=>		$feature_name,
				'columns'			=>		$columns,
			]);
			
		}catch(Exception $e){
			throw new Exception("Something went wrong!");
		}
	}

}