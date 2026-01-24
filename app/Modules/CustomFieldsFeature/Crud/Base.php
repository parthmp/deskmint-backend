<?php

namespace App\Modules\CustomFieldsFeature\Crud;

use App\Helpers\General;
use App\Modules\CustomFieldsFeature\DatabaseOperations\DatabaseOperations;
use App\Modules\CustomFieldsFeature\Exceptions\InvalidFieldsException;
use App\Modules\CustomFieldsFeature\Exceptions\LabelCharException;
use App\Modules\CustomFieldsFeature\Exceptions\LabelFoundException;
use App\Repositories\CustomFieldType\CustomFieldTypeRepository;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Exception;

/**
 * Base class
 */
class Base{

	public function __construct(private CustomFieldTypeRepository $custom_field_type_repository, private SettingsSectionRepository $settings_section_repository){}

	public function saveOrUpdateCustomField(array $data, string $feature_custom_fields_model, string $slug, bool $add, string $type, string $custom_id , mixed $object = null) : bool {
		
		$db = new DatabaseOperations($feature_custom_fields_model);

		$company_id = $data['company_id'];
		
		if(!$add){
			if(!$data['past_label']){
				throw new InvalidFieldsException("Invalid request", "invalid_request");
			}
		}
		
		$input_field = $data['input_field'];
		
		$field = $this->custom_field_type_repository->fetchById($input_field);
		
		if(!$field){
			throw new InvalidFieldsException("Invalid request", "invalid_request");
		}

		$options = '';
		if(strtolower($field->input_type) === 'select' || strtolower($field->input_type) === 'multiselect'){

			if(!$data['select_options']){
				throw new InvalidFieldsException("Please fill options", "invalid_data");
			}

			$options_temp = $data['select_options'];
			if($options_temp === ''){
				throw new InvalidFieldsException("Please fill options", "invalid_data");
			}

			$options = $options_temp;

		}

		$label = (string) $data['label'];
		if(strlen($label) > 50){
			throw new LabelCharException("Label must not have more than 50 characters", "invalid_label");
		}
		
		/* check if label exists already */

		if($add){
			$found_label = $db->fetchCustomFieldById($company_id, $label);
		}else{
			$found_label = $db->fetchCustomFieldByIdExcludingRecord($company_id, $label, $object->label);
		}

		if($found_label){
			throw new LabelFoundException("Label already exists", "invalid_label");
		}
		
		$placeholder = '';
		if($data['placeholder']){
			$placeholder = $data['placeholder'];
		}

		$is_required = $data['is_required'];

		$default_value = '';
		if($data['default_value']){
			$default_value = $data['default_value'];
		}
		
		$add_edit_page_order = $data['add_edit_page_order'];

		$is_required_flag = 0;
		if((string) $is_required === 'true'){
			$is_required_flag = 1;
		}

		try{

			$flag1 = $db->createOrUpdate([
				'id'					=>		$field->id,
				'company_id'			=>		$company_id,
				'label'					=>		$label,
				'placeholder'			=>		$placeholder,
				'is_required_flag'		=>		$is_required_flag,
				'options'				=>		$options,
				'default_value'			=>		$default_value,
				'add_edit_page_order'	=>		$add_edit_page_order,
				'slug'					=>		$slug,
				'past_label'			=>		$data['past_label'],
				'input_type'			=>		$field->input_type
			], $add, $object);

			$flag2 = $this->modifyArrangedFieldsSettings($type, $company_id, $custom_id, $feature_custom_fields_model);

			return $flag1 && $flag2;

		}catch(Exception $e){
			throw new Exception("failed to save / update the record");
		}
	}

	/**
	 * modifyArrangedFieldsSettings function
	 *
	 * @param string $type
	 * @param integer $company_id
	 * @param string $custom_id
	 * @param string $custom_fields_table_modal
	 * @return void
	 */
	public function modifyArrangedFieldsSettings(string $type, int $company_id, string $custom_id, string $custom_fields_table_modal) : bool {

		$db = new DatabaseOperations($custom_fields_table_modal);

		$field = $this->settings_section_repository->fetchSettings($company_id, $type);

		if($field){

			$new_json = [];

			$json = json_decode($field->settings_json, true);

			$custom_fields = $db->fetchCustomFieldsArrayByCompanyId($company_id);

			$ids = [];

			foreach($custom_fields as $c_field){
				$ids[] = $c_field['id'];
			}

			for($z = 0 ; $z < count($json) ; $z++){

				$fine_to_push = true;

				if(isset($json[$z][$custom_id]) && !in_array($json[$z][$custom_id], $ids)){
					$fine_to_push = false;
				}

				for($x = 0 ; $x < count($custom_fields) ; $x++){

					if(isset($json[$z][$custom_id])){

						if((int) $json[$z][$custom_id] === (int) $custom_fields[$x]['id'] && $json[$z]['type'] === 'custom'){
							
							$json[$z]['text'] = $custom_fields[$x]['label'];
							$json[$z]['type'] = 'custom';
							$json[$z]['value'] = General::replaceWithUnderscores($custom_fields[$x]['label']);
							$json[$z]['mapped'] = null;
							$json[$z][$custom_id] = $custom_fields[$x]['id'];

						}

					}

				}

				if($fine_to_push){
					$new_json[] = $json[$z];
				}

			}

			$new_json = json_encode($new_json);
			$field->settings_json = $new_json;
			return $field->save();

		}

		return true;

	}

}