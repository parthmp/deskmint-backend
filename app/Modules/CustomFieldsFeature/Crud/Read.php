<?php

namespace App\Modules\CustomFieldsFeature\Crud;

use Illuminate\Database\Eloquent\Collection;

/**
 * Read class
 */
class Read{

	/**
	 * fetchFieldTypes function
	 *
	 * @param string $model
	 * @return Collection
	 */
	public function fetchFieldTypes(string $model) : Collection {

		$fields = $model::select(['id', 'input_type', 'input_name'])->orderBy('input_name', 'asc')->get();
		
		$fields = $fields->each(function($field){
			$field->text = ucfirst($field->input_type).' - '.$field->input_name;
			$field->value = $field->id;
		});

		return $fields;

	}

}