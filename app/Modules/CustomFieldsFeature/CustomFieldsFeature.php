<?php

namespace App\Modules\CustomFieldsFeature;

use App\Modules\CustomFieldsFeature\Crud\Read;
use Illuminate\Database\Eloquent\Collection;

class CustomFieldsFeature{

	private string $model;

	public function __construct(private Read $read){}

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

}