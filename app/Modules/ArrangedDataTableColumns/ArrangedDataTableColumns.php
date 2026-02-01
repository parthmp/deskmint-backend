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
	 * @return array
	 */
	public function fetchArrangedColumnsData(Request $request, string $feature_name, string $original_table, string $custom_fields_model, string $type) : array {
		return $this->fetch->fetchArrangedColumnsData($request, $feature_name, $original_table, $custom_fields_model, $type);
	}
	
	/**
	 * saveArrangedColumnsData function
	 *
	 * @param Request $request
	 * @param string $custom_fields_model
	 * @param string $feature_name
	 * @param string $original_table
	 * @param string $type
	 * @return boolean
	 */
	public function saveArrangedColumnsData(Request $request, string $custom_fields_model, string $feature_name, string $original_table, string $type) : bool {
		return $this->save->saveArrangedColumnsData($request, $custom_fields_model, $feature_name, $original_table, $type);
	}

}