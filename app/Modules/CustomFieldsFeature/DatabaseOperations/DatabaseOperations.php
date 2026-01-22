<?php

namespace App\Modules\CustomFieldsFeature\DatabaseOperations;

use App\Modules\CustomFieldsFeature\FlatTable\FlatTable;
use Illuminate\Database\Eloquent\Model;

/**
 * DatabaseOperations class
 */
class DatabaseOperations{

	private $feature_custom_fields_model;

	/**
	 * __construct function
	 *
	 * @param string $feature_custom_fields_model
	 */
	public function __construct(string $feature_custom_fields_model){
		$this->feature_custom_fields_model = $feature_custom_fields_model;
	}

	/**
	 * fetchCustomFieldById function
	 *
	 * @param integer $company_id
	 * @param string $label
	 * @return Model
	 */
	public function fetchCustomFieldById(int $company_id, string $label) : Model {
		return $this->feature_custom_fields_model::where([['company_id', '=', $company_id], ['label', '=', trim($label)]])->first();
	}

	/**
	 * fetchCustomFieldByIdExcludingRecord function
	 *
	 * @param integer $company_id
	 * @param string $label
	 * @param string $exclude_label
	 * @return Model
	 */
	public function fetchCustomFieldByIdExcludingRecord(int $company_id, string $label, string $exclude_label) : Model {
		return $this->feature_custom_fields_model::where([['company_id', '=', $company_id], ['label', '=', trim($label)], ['label', '<>', $exclude_label]])->first();
	}

	/**
	 * createOrUpdate function
	 *
	 * @param array $data
	 * @param boolean $add
	 * @param Model|null $object
	 * @return boolean
	 */
	public function createOrUpdate(array $data, bool $add, ?String $object) : bool {
		
		if($add){
			$ccf = new $this->feature_custom_fields_model();
		}else{
			$ccf = $object;
			$object = null;
		}

		$ccf->custom_field_type_id = $data['id'];
		$ccf->company_id = $data['company_id'];
		$ccf->label = $data['label'];
		$ccf->placeholder = $data['placeholder'];
		$ccf->required = $data['is_required_flag'];
		$ccf->type_params = $data['options'];
		$ccf->default_value = $data['default_value'];
		$ccf->order_on_add_edit_page = (int) $data['add_edit_page_order'];

		/* handle flat table */
		$flat_table = new FlatTable($data['slug'].'s_flat', $data['slug'].'s', $data['slug'].'_id');
		if($add){
			$flat_table->addFlatTableColumn($data['label'], $data['input_type']);
		}else{
			$past_label = (string) $data['past_label'];
			$flat_table->editFlatTableColumn($past_label, $data['label'], $data['input_type']);
		}
		/**/
		return $ccf->save();
	}

	/**
	 * fetchCustomFieldsArrayByCompanyId function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchCustomFieldsArrayByCompanyId(int $company_id) : array {
		return $this->feature_custom_fields_model::where('company_id', '=', $company_id)->get()->toArray();
	}

}