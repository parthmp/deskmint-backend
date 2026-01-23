<?php

namespace App\Modules\CustomFieldsFeature\Crud;

use App\Modules\CustomFieldsFeature\DatabaseOperations\DatabaseOperations;
use App\Modules\CustomFieldsFeature\Exceptions\RecordNotFoundException;

/**
 * Update class
 */
class Update extends Base{

	/**
	 * updateData function
	 *
	 * @param array $data
	 * @param string $feature_custom_fields_model
	 * @param string $slug
	 * @param integer $id
	 * @param string $type
	 * @param string $custom_id
	 * @return boolean
	 */
	public function updateData(array $data, string $feature_custom_fields_model, string $slug, int $id, string $type, string $custom_id) : bool {
		
		$db = new DatabaseOperations($feature_custom_fields_model);

		$custom_field = $db->fetchCustomFieldByIdAndCompanyId($id, $data['company_id']);

		if(!$custom_field){
			throw new RecordNotFoundException("record not found");
		}
		
		return $this->saveOrUpdateCustomField($data, $feature_custom_fields_model, $slug, false, $type, $custom_id, $custom_field);	

	}

}