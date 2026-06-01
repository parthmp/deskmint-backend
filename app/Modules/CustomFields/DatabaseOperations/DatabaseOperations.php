<?php

namespace App\Modules\CustomFields\DatabaseOperations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseOperations{
	
	/**
	 * getDBCustomFields function
	 *
	 * @param integer $db_id
	 * @param string $custom_fields_model
	 * @param integer $company_id
	 * @param boolean $add
	 * @param string $type
	 * @return Collection
	 */
	public function getDBCustomFields(int $db_id, string $custom_fields_model, int $company_id, bool $add, string $type) : Collection {
		
		// $db_custom_fields = $custom_fields_model::where('company_id', $company_id)->whereHas('customFieldType')->with(['customFieldValue' => function($query) use ($db_id, $type) {
        // 	$query->where($type . '_id', $db_id);
    	// }])->get();

		$db_custom_fields = $custom_fields_model::where('company_id', $company_id)->whereHas('customFieldType')->with([
        'customFieldType',
        'customFieldValue' => function($query) use ($db_id, $type) {
            $query->where($type . '_id', $db_id);
        }])->get();

		return $db_custom_fields;

	}

	/**
	 * upsertCustomFields function
	 *
	 * @param string $model
	 * @param array $records
	 * @param array $update_by
	 * @param array $columns
	 * @return void
	 */
	public function upsertCustomFields(string $model, array $records, array $update_by, array $columns) : void {
		$model::upsert($records, $update_by, $columns);
	}

	/**
	 * deletionForFlatTableWithType function
	 *
	 * @param string $flat_table
	 * @param string $id_column
	 * @param integer $db_id
	 * @return void
	 */
	public function deletionForFlatTableWithType(string $flat_table, string $id_column, int $db_id) : void {
		DB::table($flat_table)->where($id_column, '=', $db_id)->delete();
	}

	/**
	 * insertForFlatTable function
	 *
	 * @param string $flat_table
	 * @param array $insert_flat
	 * @return void
	 */
	public function insertForFlatTable(string $flat_table, array $insert_flat) : void {
		DB::table($flat_table)->insert($insert_flat);
	}

}