<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Models\CustomFieldType;
use App\Services\DataTable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use SebastianBergmann\CodeCoverage\Report\Html\CustomCssFile;

class ClientsCustomFieldsController extends Controller{
    
	public function fetchFieldTypes(Request $request){

		$fields = CustomFieldType::select(['id', 'input_type', 'input_name'])->orderBy('input_name', 'asc')->get();
		
		$fields = $fields->each(function($field){
			$field->text = ucfirst($field->input_type).' - '.$field->input_name;
			$field->value = $field->id;
		});

		return $fields;

	}

	public function store(Request $request){
		
		$v = Validator::make($request->all(), [
			'input_field'			=>		'required',
			'label'					=>		'required',
			'is_required'			=>		'required',
			'show_on_index'			=>		'required',
			'add_edit_page_order'	=>		'required',
			'column_order'			=>		'required'
		]);

		if($v->fails()){
			return response(['message' => 'Please fill in required fields', 'validity' => 'invalid_data'], config('global.error_code'));
		}

		$input_field = Sanitize::input($request->input('input_field'));

		
		$field = CustomFieldType::where('id', '=', $input_field)->first();
		if(!$field){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$options = '';
		if(strtolower($field->input_type) === 'select'){

			
			if(!$request->filled('select_options')){
				return response(['message' => 'Please fill options', 'validity' => 'invalid_data'], config('global.error_code'));
			}

			$options_temp = Sanitize::input($request->input('select_options'));
			if($options_temp === ''){
				return response(['message' => 'Please fill options', 'validity' => 'invalid_data'], config('global.error_code'));
			}

			$options = $options_temp;

		}

		$company_id = Sanitize::input($request->input('company_id'));
		$label = Sanitize::input($request->input('label'));
		
		$placeholder = '';
		if($request->filled('placeholder')){
			$placeholder = Sanitize::input($request->input('placeholder'));
		}

		$is_required = Sanitize::input($request->input('is_required'));

		$default_value = '';
		if($request->filled('default_value')){
			$default_value = Sanitize::input($request->input('default_value'));
		}
		
		$show_on_index = Sanitize::input($request->input('show_on_index'));
		$add_edit_page_order = Sanitize::input($request->input('add_edit_page_order'));
		$column_order = Sanitize::input($request->input('column_order'));
		
		try{

			$is_required_flag = 0;
			if((string)$is_required === 'true'){
				$is_required_flag = 1;
			}

			$show_on_index_flag = 0;
			if((string)$show_on_index === 'true'){
				$show_on_index_flag = 1;
			}

			$ccf = new ClientsCustomField();
			$ccf->custom_field_type_id = $field->id;
			$ccf->company_id = $company_id;
			$ccf->label = $label;
			$ccf->placeholder = $placeholder;
			$ccf->required = $is_required_flag;
			$ccf->type_params = $options;
			$ccf->default_value = $default_value;
			$ccf->order_on_add_edit_page = (int)$add_edit_page_order;
			$ccf->order_column_on_index_page = (int)$column_order;
			$ccf->show_on_index_page = $show_on_index_flag;
			$ccf->searchable_created_at = General::generateSearchDateText(now());
			
			if($ccf->save()){
				return response(['message' => 'Custom field created successfully', 'validity' => 'created_success'], 200);
			}else{
				return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
			}

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
		
		$company_id = Sanitize::input($request->input('company_id'));

		$fields = DataTable::sortNPaginate(
			$request,
			\App\Models\ClientsCustomField::class,
			['deleted_at', 'updated_at'],
			$company_id,
			[
				[
					'table' => 'custom_field_types',
					'first' => 'clients_custom_fields.custom_field_type_id',
					'operator' => '=',
					'second' => 'custom_field_types.id',
					'columns' => ['custom_field_types.input_type as input_type']
				]
			], [
				'clients_custom_fields.required' => [
					0	=>	'No',
					1	=>	"Yes"
				]
			]
		);
		
		$fields->each(function($ele){
			$ele->input_type = ucfirst($ele->input_type);
			if($ele->required === 0){
				$ele->required = [
					'type'		=>	'label',
					'highlight'	=>	'error',
					'text'		=>	'No'
				];
			}else{
				$ele->required = [
					'type'		=>	'label',
					'highlight'	=>	'success',
					'text'		=>	'Yes'
				];
			}
		});
		
		$table_data = [
			'columns' => [
				[
					'label' => 	'id',
					'text'	=>	'ID'
				],
				[
					'label' => 	'input_type',
					'text'	=>	'Field type'
				],
				[
					'label' => 	'label',
					'text'	=>	'Label'
				],
				[
					'label' => 	'required',
					'text'	=>	'Required'
				],
				[
					'label' => 	'created_at',
					'text'	=>	'Added on'
				],
				[
					'label'	=> 'actions',
					'text'	=> 'Actions'
				]
			],
			'rows' => $fields->items()
		];

		$total_pages = $fields->lastPage();

		return [
			'table_data'	=>		$table_data,
			'total_pages'	=>		$total_pages,
			'current_page'	=>		$fields->currentPage()
		];

	}

}
