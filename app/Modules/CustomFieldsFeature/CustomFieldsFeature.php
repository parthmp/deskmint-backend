<?php

namespace App\Modules\CustomFieldsFeature;

use App\Modules\CustomFieldsFeature\Crud\Create;
use App\Modules\CustomFieldsFeature\Crud\Delete;
use App\Modules\CustomFieldsFeature\Crud\Read;
use App\Modules\CustomFieldsFeature\Crud\Update;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CustomFieldsFeature{

	private string $model;

	public function __construct(private Read $read, private Create $create, private Update $update, private Delete $delete){}

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
	 * @param string $slug
	 * @param boolean $add
	 * @param string $type
	 * @param string $custom_id
	 * @param mixed $object
	 * @return boolean
	 */
	public function saveOrUpdateCustomField(array $data, string $slug, bool $add, string $type, string $custom_id , mixed $object = null) : bool {
		return $this->create->saveOrUpdateCustomField($data, $this->model, $slug, $add, $type, $custom_id , $object);
	}

	/**
	 * indexData function
	 *
	 * @param array $data
	 * @param string $slug
	 * @return array
	 */
	public function indexData(array $data, string $slug) : array {
		return $this->read->indexData($data, $this->model, $slug);
	}

	/**
	 * showData function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return Model
	 */
	public function showData(int $company_id, int $id) : Model {
		return $this->read->showData($this->model, $company_id, $id);
	}

	/**
	 * updateData function
	 *
	 * @param array $data
	 * @param string $slug
	 * @param integer $id
	 * @param string $type
	 * @param string $custom_id
	 * @return boolean
	 */
	public function updateData(array $data, string $slug, int $id, string $type, string $custom_id) : bool {
		return $this->update->updateData($data, $this->model, $slug, $id, $type, $custom_id);
	}

	/**
	 * destroyData function
	 *
	 * @param array $data
	 * @param string $slug
	 * @param string $type
	 * @param integer $company_id
	 * @param string $custom_id
	 * @return boolean|null
	 */
	public function destroyData(array $data, string $slug, string $type, int $company_id, string $custom_id) : ?bool {
		return $this->delete->destroyData($data, $this->model, $slug, $type, $company_id, $custom_id);
	}

}