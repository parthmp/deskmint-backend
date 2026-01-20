<?php

namespace App\Modules\CustomFieldsFeature\Crud;

use App\Modules\CustomFieldsFeature\DatabaseOperations\DatabaseOperations;
use App\Modules\CustomFieldsFeature\Exceptions\InvalidFieldsException;
use App\Modules\CustomFieldsFeature\Exceptions\LabelCharException;
use App\Modules\CustomFieldsFeature\Exceptions\LabelFoundException;
use App\Modules\CustomFieldsFeature\FlatTable\FlatTable;
use App\Repositories\CustomFieldType\CustomFieldTypeRepository;
use Exception;

/**
 * Base class
 */
class Base{

	public function __construct(private CustomFieldTypeRepository $custom_field_type_repository){}

	public function saveOrUpdateCustomField(array $data, string $feature_custom_fields_model, string $slug, bool $add, string $type, string $custom_id , mixed $object = null) : mixed {
		
		$company_id = $data['company_id'];
		
		if(!$add){
			if(!$data['past_label']){
				return false;
			}
		}
		
		$input_field = $data['input_field'];
		
		$field = $this->custom_field_type_repository->fetchById($input_field);
		
		if(!$field){
			return false;
		}

		$options = '';
		if(strtolower($field->input_type) === 'select' || strtolower($field->input_type) === 'multiselect'){

			if(!$data['select_options']){
				throw new InvalidFieldsException("Please fill options");
			}

			$options_temp = $data['select_options'];
			if($options_temp === ''){
				throw new InvalidFieldsException("Please fill options");
			}

			$options = $options_temp;

		}

		$label = (string) $data['label'];
		if(strlen($label) > 50){
			throw new LabelCharException("Label must not have more than 50 characters");
		}
		
		/* check if label exists already */

		$db = new DatabaseOperations($feature_custom_fields_model);

		if($add){
			$found_label = $db->fetchCustomFieldById($company_id, $label);
		}else{
			$found_label = $db->fetchCustomFieldByIdExcludingRecord($company_id, $label, $object->label);
		}

		if($found_label){
			throw new LabelFoundException("Label already exists");
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
			
			$db->createOrUpdate([

			], $add, $feature_custom_fields_model);

			// if($add){
			// 	$ccf = new $feature_custom_fields_model();
			// }else{
			// 	$ccf = $object;
			// 	$object = null;
			// }

			// $ccf->custom_field_type_id = $field->id;
			// $ccf->company_id = $company_id;
			// $ccf->label = $label;
			// $ccf->placeholder = $placeholder;
			// $ccf->required = $is_required_flag;
			// $ccf->type_params = $options;
			// $ccf->default_value = $default_value;
			// $ccf->order_on_add_edit_page = (int)$add_edit_page_order;

			// $success_message = 'Custom field updated successfully';
			// $validity_message = 'updated_success';

			// if($add){

			// 	$success_message = 'Custom field created successfully';
			// 	$validity_message = 'created_success';

			// }

			// /* handle flat table */
			// $flat_table = new FlatTable($slug.'s_flat', $slug.'s', $slug.'_id');
			// if($add){
			// 	$flat_table->addFlatTableColumn($label, $field->input_type);
			// }else{
			// 	$past_label = (string) $data['past_label'];
			// 	$flat_table->editFlatTableColumn($past_label, $label, $field->input_type);
			// }
			// /**/

			// if($ccf->save()){

			// 	//$this->modifyArrangedFieldsSettings($type, $company_id, $custom_id, $feature_custom_fields_model);

			// 	return response(['message' => $success_message, 'validity' => $validity_message], 200);
			// }else{
			// 	return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
			// }


		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
		}
	}

}