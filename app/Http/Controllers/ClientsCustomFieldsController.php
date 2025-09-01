<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Models\CustomFieldType;
use App\Services\DataTable;
use App\Services\ManageFlatTable;
use DateTimeZone;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
		
		$company_id = Sanitize::input($request->input('company_id'));
		return $this->addOrUpdateCustomClientsField($request, $company_id, true);
	
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
			['clients_custom_fields.created_at'],
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

			if((int)$ele->required === 0){
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
					'text'	=>	'ID#'
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

	public function show(Request $request){

		$id = $request->segment(3);

		$company_id = Sanitize::input($request->input('company_id'));
		
		$client_custom_field = ClientsCustomField::select('custom_field_type_id', 'label', 'placeholder', 'required', 'default_value', 'order_on_add_edit_page', 'type_params')->where([['id', '=', $id], ['company_id','=', $company_id]])->with('customFieldType')->first();
		if(!$client_custom_field){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return $client_custom_field;

	}

	private function addOrUpdateCustomClientsField(Request $request, $company_id, $add = true, $object = null){

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

			$found_label = ClientsCustomField::where([['company_id', '=', $company_id], ['label', '=', trim($label)]])->first();
			

		}else{

			$found_label = ClientsCustomField::where([['company_id', '=', $company_id], ['label', '=', trim($label)], ['label', '<>', $object->label]])->first();

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
				$ccf = new ClientsCustomField();
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
			$flat_table = new ManageFlatTable('clients_flat', 'clients', 'client_id');
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

	public function update(Request $request){

		$id = $request->segment(3);

		$company_id = Sanitize::input($request->input('company_id'));

		$client_custom_field = ClientsCustomField::where([['id', '=', $id], ['company_id','=', $company_id]])->first();

		if(!$client_custom_field){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return $this->addOrUpdateCustomClientsField($request, $company_id, false, $client_custom_field);		

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
			
			$flat_table = new ManageFlatTable('clients_flat', 'clients', 'client_id');

			$column_names = ClientsCustomField::whereIn('id', $ids)->get();

			$column_names_arranged = [];

			foreach($column_names as $column){
				$column_names_arranged[] = $column->label;
			}

			$flat_table->dropColumns($column_names_arranged);
			$column_names_arranged = null;
			

			ClientsCustomField::whereIn('id', $ids)->delete();
			

			return response(['message' => 'Custom field(s) deleted successfully', 'validity' => 'delete_success'], 200);

		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], 500);
		}

	}

}
