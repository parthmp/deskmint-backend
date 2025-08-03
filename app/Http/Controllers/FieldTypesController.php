<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\CustomFieldType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FieldTypesController extends Controller{
	
	public function getInputTypes(Request $request){

		$input_types = [];

		foreach(config('global.field_types') as $key => $type){

			$input_types[] = [
				'value'	=>	$type,
				'text'	=>	ucfirst($key)
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

		$input_type = Sanitize::input($request->input('input_type').'');
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
			'default_per_page'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$mapped_array = config('global.field_types');
		$mapped_array = array_flip($mapped_array);

		$paginate = false;

		if($request->has('page_data')){
			$page_data = json_decode(Sanitize::input($request->input('page_data')));
			if(json_last_error() === JSON_ERROR_NONE && $page_data !== null && $page_data !== ''){
				$paginate = true;
			}
		}

		$default_per_page = Sanitize::input($request->input('default_per_page'));

		$fields = CustomFieldType::query();
		
		$current_page = 1;

		if($paginate){
			
			
			$searched_term = trim($page_data->searched_term);
			if(isset($page_data->current_page)){
				$current_page = $page_data->current_page;
			}
			
			$sorted_column = $page_data->sorted_column;
			
			if($searched_term !== ''){
				
				$fields = $fields->where('input_name', 'LIKE', '%'.$searched_term.'%');

				$matching_type_ids = [];
				foreach(config('global.field_types') as $type_name => $type_id){
					if(stripos($type_name, $searched_term) !== false){
						$matching_type_ids[] = $type_id;
					}
				}

				if(!empty($matching_type_ids)){
                	$fields->orWhereIn('input_type', $matching_type_ids);
            	}
				
			}

			if($sorted_column !== ''){
				if(isset($sorted_column->label) && isset($sorted_column->sort_visibility)){
					$fields->orderBy($sorted_column->label, $sorted_column->sort_visibility);
				}
				
			}

			if(isset($page_data->per_page)){
				$fields = $fields->paginate($page_data->per_page, ['*'], 'page', $current_page);
			}else{
				$fields = $fields->paginate($default_per_page, ['*'], 'page', $current_page);
			}

		}else{
			$fields = $fields->paginate($default_per_page, ['*'], 'page', $current_page);
		}

		$fields->each(function($ele) use ($mapped_array){
			$ele->input_type = ucfirst($mapped_array[$ele->input_type]);
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

}
