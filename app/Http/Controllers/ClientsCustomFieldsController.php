<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldType;
use Illuminate\Http\Request;

class ClientsCustomFieldsController extends Controller{
    
	public function fetchFieldTypes(Request $request){

		$fields = CustomFieldType::select(['id', 'input_type', 'input_name'])->orderBy('input_name', 'asc')->get();
		
		$fields = $fields->each(function($field){
			$field->text = ucfirst($field->input_type).' - '.$field->input_name;
			$field->value = $field->id;
		});

		return $fields;

	}

}
