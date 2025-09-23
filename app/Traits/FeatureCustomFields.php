<?php

namespace App\Traits;

use App\Helpers\Sanitize;
use App\Models\CustomFieldType;
use App\Services\ManageFlatTable;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

trait FeatureCustomFields{

	public function fetchFieldTypesData(string $model) : Collection{

		$fields = $model::select(['id', 'input_type', 'input_name'])->orderBy('input_name', 'asc')->get();
		
		$fields = $fields->each(function($field){
			$field->text = ucfirst($field->input_type).' - '.$field->input_name;
			$field->value = $field->id;
		});

		return $fields;

	}

	public function saveOrUpdateCustomField(Request $request, string $feature_custom_fields_model, int $company_id, string $slug, bool $add, mixed $object = null) : Response{

		$v = Validator::make($request->all(), [
			'input_field'			=>		'required',
			'label'					=>		'required',
			'is_required'			=>		'required',
			'add_edit_page_order'	=>		'required'
		]);
		
		if($v->fails()){
			return response(['message' => 'Please fill in required fields', 'validity' => 'invalid_data'], config('global.error_code'));
		}
		
		if(!$add){
			if(!$request->filled('past_label')){
				return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}
		}

		/* check if label has any special chars */
		$v = Validator::make($request->all(), [
			'label'					=>		'required|regex:/^[a-zA-Z0-9 ]*$/'
		]);
		if($v->fails()){
			return response(['message' => 'Only letters and numbers allowed for label', 'validity' => 'invalid_label_chars'], config('global.error_code'));
		}
		
		$input_field = Sanitize::input($request->input('input_field'));
		
		$field = CustomFieldType::where('id', '=', $input_field)->first();
		
		if(!$field){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$options = '';
		if(strtolower($field->input_type) === 'select' || strtolower($field->input_type) === 'multiselect'){

			if(!$request->filled('select_options')){
				return response(['message' => 'Please fill options', 'validity' => 'invalid_data'], config('global.error_code'));
			}

			$options_temp = Sanitize::input($request->input('select_options'));
			if($options_temp === ''){
				return response(['message' => 'Please fill options', 'validity' => 'invalid_data'], config('global.error_code'));
			}

			$options = $options_temp;

		}

		$label = Sanitize::input($request->input('label'));
		if(strlen($label) > 50){
			return response(['message' => 'Label must not have more than 50 characters', 'validity' => 'invalid_label'], config('global.error_code'));
		}
		
		/* check if label exists already */
		if($add){

			$found_label = $feature_custom_fields_model::where([['company_id', '=', $company_id], ['label', '=', trim($label)]])->first();
			

		}else{

			$found_label = $feature_custom_fields_model::where([['company_id', '=', $company_id], ['label', '=', trim($label)], ['label', '<>', $object->label]])->first();

		}

		if($found_label){
			return response(['message' => 'Label already exists', 'validity' => 'invalid_label'], config('global.error_code'));
		}
		
		$placeholder = '';
		if($request->filled('placeholder')){
			$placeholder = Sanitize::input($request->input('placeholder'));
		}

		$is_required = Sanitize::input($request->input('is_required'));

		$default_value = '';
		if($request->filled('default_value')){
			$default_value = Sanitize::input($request->input('default_value'));
		}
		
		$add_edit_page_order = Sanitize::input($request->input('add_edit_page_order'));

		$is_required_flag = 0;
		if((string)$is_required === 'true'){
			$is_required_flag = 1;
		}

		try{
			
			if($add){
				$ccf = new $feature_custom_fields_model();
			}else{
				$ccf = $object;
				$object = null;
			}

			$ccf->custom_field_type_id = $field->id;
			$ccf->company_id = $company_id;
			$ccf->label = $label;
			$ccf->placeholder = $placeholder;
			$ccf->required = $is_required_flag;
			$ccf->type_params = $options;
			$ccf->default_value = $default_value;
			$ccf->order_on_add_edit_page = (int)$add_edit_page_order;

			$success_message = 'Custom field updated successfully';
			$validity_message = 'updated_success';

			if($add){

				$success_message = 'Custom field created successfully';
				$validity_message = 'created_success';

			}

			/* handle flat table */
			$flat_table = new ManageFlatTable($slug.'s_flat', $slug.'s', $slug.'_id');
			if($add){
				$flat_table->addFlatTableColumn($label, $field->input_type);
			}else{
				$past_label = Sanitize::input($request->input('past_label'));
				$flat_table->editFlatTableColumn($past_label, $label, $field->input_type);
			}
			/**/

			if($ccf->save()){
				return response(['message' => $success_message, 'validity' => $validity_message], 200);
			}else{
				return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
			}


		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
		}
	}

}