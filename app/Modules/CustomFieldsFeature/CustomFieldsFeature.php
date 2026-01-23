<?php

namespace App\Modules\CustomFieldsFeature;

use App\Modules\CustomFieldsFeature\Crud\Create;
use App\Modules\CustomFieldsFeature\Crud\Read;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CustomFieldsFeature{

	private string $model;

	public function __construct(private Read $read, private Create $create){}

	/**
	 * setModel function
	 *
	 * @param string $model
	 * @return self
	 */
	public function setModel(string $model) : self {
		$this->model = $model;
		return $this;
	}

	/**
	 * fetchFieldTypes function
	 *
	 * @return Collection
	 */
	public function fetchFieldTypes() : Collection {
		return $this->read->fetchFieldTypes($this->model);
	}

	/**
	 * saveOrUpdateCustomField function
	 *
	 * @param array $data
	 * @param string $feature_custom_fields_model
	 * @param string $slug
	 * @param boolean $add
	 * @param string $type
	 * @param string $custom_id
	 * @param mixed $object
	 * @return boolean
	 */
	public function saveOrUpdateCustomField(array $data, string $feature_custom_fields_model, string $slug, bool $add, string $type, string $custom_id , mixed $object = null) : bool {
		return $this->create->saveOrUpdateCustomField($data, $feature_custom_fields_model, $slug, $add, $type, $custom_id , $object);
	}

	/**
	 * indexData function
	 *
	 * @param array $data
	 * @param string $feature_custom_fields_model
	 * @param string $slug
	 * @return array
	 */
	public function indexData(array $data, string $feature_custom_fields_model, string $slug) : array {
		return $this->read->indexData($data, $feature_custom_fields_model, $slug);
	}

	/**
	 * showData function
	 *
	 * @param string $feature_custom_fields_model
	 * @param integer $company_id
	 * @param integer $id
	 * @return Model
	 */
	public function showData(string $feature_custom_fields_model, int $company_id, int $id) : Model {
		return $this->read->showData($feature_custom_fields_model, $company_id, $id);
	}

}