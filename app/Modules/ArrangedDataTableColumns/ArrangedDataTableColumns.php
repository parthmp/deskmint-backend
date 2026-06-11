<?php

namespace App\Modules\ArrangedDataTableColumns;

use App\Modules\ArrangedDataTableColumns\Actions\Fetch;
use App\Modules\ArrangedDataTableColumns\Actions\Save;
use Illuminate\Http\Request;

class ArrangedDataTableColumns{

	public function __construct(private Fetch $fetch, private Save $save){}

	/**
	 * fetchArrangedColumnsData function
	 *
	 * @param Request $request
	 * @param string $feature_name
	 * @param string $original_table
	 * @param string $custom_fields_model
	 * @param string $type
	 * @param array $remove_columns
	 * @param array $additional_fields
	 * @return array
	 */
	public function fetchArrangedColumnsData(Request $request, string $feature_name, string $original_table, string $custom_fields_model, string $type, array $remove_columns = [], array $additional_fields = []) : array {
		return $this->fetch->fetchArrangedColumnsData($request, $feature_name, $original_table, $custom_fields_model, $type, $remove_columns, $additional_fields);
	}
	
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
	public function saveArrangedColumnsData(Request $request, string $custom_fields_model, string $feature_name, string $original_table, string $type, array $additional_fields = [], array $date_fields = []) : bool {
		return $this->save->saveArrangedColumnsData($request, $custom_fields_model, $feature_name, $original_table, $type, $additional_fields, $date_fields);
	}

}