<?php

namespace App\Repositories\CustomFieldType;

use App\Models\CustomFieldType;

class CustomFieldTypeRepository{
	
	/**
	 * create function
	 *
	 * @param string $input_type
	 * @param string $input_name
	 * @return boolean
	 */
	public function create(string $input_type, string $input_name) : bool {

		$custom_type = new CustomFieldType();
		$custom_type->input_type = $input_type;
		$custom_type->input_name = $input_name;

		return $custom_type->save();

	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return CustomFieldType|null
	 */
	public function fetchById(int $id) : CustomFieldType|null {
		return CustomFieldType::select('id', 'input_type', 'input_name')->where('id', '=', $id)->first();
	}

	/**
	 * updateByObj function
	 *
	 * @param string $input_type
	 * @param string $input_name
	 * @param CustomFieldType $obj
	 * @return boolean
	 */
	public function updateByObj(string $input_type, string $input_name, CustomFieldType $obj) : bool {
		$obj->input_type = $input_type;
		$obj->input_name = $input_name;
		return $obj->save();
	}

}