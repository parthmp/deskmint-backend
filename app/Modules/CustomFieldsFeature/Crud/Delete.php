<?php

namespace App\Modules\CustomFieldsFeature\Crud;

use App\Modules\CustomFieldsFeature\Exceptions\InvalidFieldsException;
use App\Modules\CustomFieldsFeature\FlatTable\FlatTable;
use Exception;

/**
 * Delete class
 */
class Delete extends Base{

	/**
	 * destroyData function
	 *
	 * @param array $data
	 * @param string $feature_custom_fields_model
	 * @param string $slug
	 * @param string $type
	 * @param integer $company_id
	 * @param string $custom_id
	 * @return boolean|null
	 */
	public function destroyData(array $data, string $feature_custom_fields_model, string $slug, string $type, int $company_id, string $custom_id) : ?bool {

		$ids = $data['ids'];
		
		if(!is_array($ids) || empty($ids)){
			throw new InvalidFieldsException("Invalid ids provided.");
		}

		foreach($ids as $id){
			if (!is_numeric($id)) {
				throw new InvalidFieldsException("All IDs must be numeric");
				break;
			}
		}

		try{
			
			$flat_table = new FlatTable($slug.'s_flat', $slug.'s', $slug.'_id');

			$column_names = $feature_custom_fields_model::whereIn('id', $ids)->get();

			$column_names_arranged = [];

			foreach($column_names as $column){
				$column_names_arranged[] = $column->label;
			}

			$flat_table->dropColumns($column_names_arranged);
			$column_names_arranged = null;
			

			$feature_custom_fields_model::whereIn('id', $ids)->delete();
			
			return $this->modifyArrangedFieldsSettings($type, $company_id, $custom_id, $feature_custom_fields_model);

		}catch(Exception $e){
			throw new Exception("Something went wrong");
		}

	}

}