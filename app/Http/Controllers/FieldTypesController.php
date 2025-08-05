<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\CustomFieldType;
use App\Services\DataTable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FieldTypesController extends Controller{
	
	public function getInputTypes(Request $request){

		$input_types = [];

		foreach(config('global.field_types') as $custom_field){

			$input_types[] = [
				'value'	=>	$custom_field,
				'text'	=>	ucfirst($custom_field)
			];

		}

		usort($input_types, function($a, $b) {
			return strcmp($a['text'], $b['text']);
		});

		return $input_types;

	}

	public function store(Request $request){
		
		$v = Validator::make($request->all(), [
			'input_type' 	=> 	'required',
			'input_name'	=>	'required'
		]);
		
		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$input_type = Sanitize::input($request->input('input_type'));
		$input_name = Sanitize::input($request->input('input_name'));

		if(!in_array($input_type, config('global.field_types'))){
			return response(['message' => 'Invalid field provided', 'validity' => 'invalid_field'], config('global.error_code'));
		}

		try{

			$custom_type = new CustomFieldType();
			$custom_type->input_type = $input_type;
			$custom_type->input_name = $input_name;

			if($custom_type->save()){
				return response(['message' => 'Custom field type created successfully', 'validity' => 'created_success'], 200);
			}

			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));

		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
		}

	}

	public function index(Request $request){
		
		$v = Validator::make($request->all(), [
			'default_per_page'	=>	'required|integer|min:1'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}
		
		$fields = DataTable::sortNPaginate($request, CustomFieldType::class, ['deleted_at', 'updated_at', 'created_at']);
		
		$fields->each(function($ele){
			$ele->input_type = ucfirst($ele->input_type);
		});
		
		$table_data = [
			'columns' => [
				[
					'label' => 	'id',
					'text'	=>	'ID'
				],
				[
					'label' => 	'input_type',
					'text'	=>	'Input type'
				],
				[
					'label'	=>	'input_name',
					'text'	=>	'Input name'
				],[
					'label'	=> 'actions',
					'text'	=> 'Actions'
				]
			],
			'rows' => $fields->items()
		];

		$total_pages = $fields->lastPage();

		return [
			'table_data'	=>		$table_data,
			'total_pages'	=>		$total_pages
		];

	}

	private function findField(Request $request){

		$field_id = Sanitize::input($request->segment(3));

		if($field_id === ''){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$field = CustomFieldType::where('id', '=', $field_id)->first();
		if(!$field){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return $field;

	}

	public function show(Request $request){

		return $this->findField($request);	

	}

	public function update(Request $request){
		
		$field = $this->findField($request);

		if($field instanceof \Illuminate\Http\Response){
        	return $field;
    	}

		$v = Validator::make($request->all(), [
			'input_type' 	=> 	'required',
			'input_name'	=>	'required'
		]);
		
		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$input_type = Sanitize::input($request->input('input_type'));
		$input_name = Sanitize::input($request->input('input_name'));

		if(!in_array($input_type, config('global.field_types'))){
			return response(['message' => 'Invalid field provided', 'validity' => 'invalid_field'], config('global.error_code'));
		}


		try{

			
			$field->input_type = $input_type;
			$field->input_name = $input_name;

			if($field->save()){
				return response(['message' => 'Custom field type updated successfully', 'validity' => 'updated_success'], 200);
			}

			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));

		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
		}

	}

	public function destroy(Request $request){

		$ids = $request->input('ids');

		if (!is_array($ids) || empty($ids)) {
			return response(['message' => 'No valid IDs provided', 'validity' => 'invalid_ids'], config('global.error_code'));
		}

		foreach ($ids as $id){
			if (!is_numeric($id)) {
				return response(['message' => 'All IDs must be numeric', 'validity' => 'non_numeric'], config('global.error_code'));
			}
		}

		try{
			
			CustomFieldType::whereIn('id', $ids)->delete();

			return response(['message' => 'Field(s) deleted successfully'], 200);

		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], 500);
		}

	}

}
