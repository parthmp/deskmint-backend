<?php

namespace App\Modules\CustomFieldsFeature;

use App\Modules\CustomFieldsFeature\Crud\Create;
use App\Modules\CustomFieldsFeature\Crud\Read;
use Illuminate\Database\Eloquent\Collection;

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

	public function saveOrUpdateCustomField(array $data, string $feature_custom_fields_model, string $slug, bool $add, string $type, string $custom_id , mixed $object = null) : bool {
		return $this->create->saveOrUpdateCustomField($data, $feature_custom_fields_model, $slug, $add, $type, $custom_id , $object);
	}

}