<?php

namespace App\Traits;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\CustomFieldType;
use App\Models\SettingsSection;
use App\Services\DataTable;
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

	public function saveOrUpdateCustomField(Request $request, string $feature_custom_fields_model, string $slug, bool $add, string $type, string $custom_id , mixed $object = null) : Response{
		
		$company_id = (int) Sanitize::input($request->input('company_id'));

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

		$label = (string) Sanitize::input($request->input('label'));
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
				$past_label = (string) Sanitize::input($request->input('past_label'));
				$flat_table->editFlatTableColumn($past_label, $label, $field->input_type);
			}
			/**/

			if($ccf->save()){

				$this->modifyArrangedFieldsSettings($type, $company_id, $custom_id, $feature_custom_fields_model);

				return response(['message' => $success_message, 'validity' => $validity_message], 200);
			}else{
				return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
			}


		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
		}
	}

	public function indexData(Request $request, string $feature_custom_fields_model, string $slug) : mixed{

		$v = Validator::make($request->all(), [
			'default_per_page'	=>	'required|integer|min:1'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}
		
		$company_id = Sanitize::input($request->input('company_id'));

		$fields = DataTable::sortNPaginate(
			$request,
			$feature_custom_fields_model,
			['deleted_at', 'updated_at'],
			$company_id,
			[$slug.'s_custom_fields.created_at'],
			[
				[
					'table' => 'custom_field_types',
					'first' => $slug.'s_custom_fields.custom_field_type_id',
					'operator' => '=',
					'second' => 'custom_field_types.id',
					'columns' => ['custom_field_types.input_type as input_type']
				]
			], [
				$slug.'s_custom_fields.required' => [
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

	public function showData(string $feature_custom_fields_model, int $company_id, int $id) : mixed{

		$id = Sanitize::input($id);
		
		$custom_field = $feature_custom_fields_model::select('custom_field_type_id', 'label', 'placeholder', 'required', 'default_value', 'order_on_add_edit_page', 'type_params')->where([['id', '=', $id], ['company_id','=', $company_id]])->with('customFieldType')->first();
		if(!$custom_field){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return $custom_field;

	}

	
	public function updateData(Request $request, string $feature_custom_fields_model, string $slug, int $id, string $type, string $custom_id) : mixed{
		
		$company_id = Sanitize::input($request->input('company_id'));

		$custom_field = $feature_custom_fields_model::where([['id', '=', $id], ['company_id','=', $company_id]])->first();
		
		if(!$custom_field){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}
		
		return $this->saveOrUpdateCustomField($request, $feature_custom_fields_model, $slug, false, $type, $custom_id, $custom_field);	

	}

	/**
	 * modifyArrangedFieldsSettings function
	 *
	 * @param string $type
	 * @param integer $company_id
	 * @param string $custom_id
	 * @param string $custom_fields_table_modal
	 * @return void
	 */
	public function modifyArrangedFieldsSettings(string $type, int $company_id, string $custom_id, string $custom_fields_table_modal) : void{

		$field = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', $type]])->first();

		if($field){

			$new_json = [];

			$json = json_decode($field->settings_json, true);

			$custom_fields = $custom_fields_table_modal::where('company_id', '=', $company_id)->get()->toArray();

			$ids = [];

			foreach($custom_fields as $c_field){
				$ids[] = $c_field['id'];
			}

			for($z = 0 ; $z < count($json) ; $z++){

				$fine_to_push = true;

				if(isset($json[$z][$custom_id]) && !in_array($json[$z][$custom_id], $ids)){
					$fine_to_push = false;
				}

				for($x = 0 ; $x < count($custom_fields) ; $x++){

					if(isset($json[$z][$custom_id])){

						if((int) $json[$z][$custom_id] === (int) $custom_fields[$x]['id'] && $json[$z]['type'] === 'custom'){
							
							$json[$z]['text'] = $custom_fields[$x]['label'];
							$json[$z]['type'] = 'custom';
							$json[$z]['value'] = General::replaceWithUnderscores($custom_fields[$x]['label']);
							$json[$z]['mapped'] = null;
							$json[$z][$custom_id] = $custom_fields[$x]['id'];

						}

					}

				}

				if($fine_to_push){
					$new_json[] = $json[$z];
				}

			}

			$new_json = json_encode($new_json);
			$field->settings_json = $new_json;
			$field->save();

		}

	}

	/**
	 * destroyData function
	 *
	 * @param Request $request
	 * @param string $feature_custom_fields_model
	 * @param string $slug
	 * @return Response
	 */
	public function destroyData(Request $request, string $feature_custom_fields_model, string $slug, string $type, int $company_id, string $custom_id) : Response{

		$ids = $request->input('ids');
		
		if(!is_array($ids) || empty($ids)){
			return response(['message' => 'No valid IDs provided', 'validity' => 'invalid_ids'], config('global.error_code'));
		}

		foreach($ids as $id){
			if (!is_numeric($id)) {
				return response(['message' => 'All IDs must be numeric', 'validity' => 'non_numeric'], config('global.error_code'));
			}
		}

		try{
			
			$flat_table = new ManageFlatTable($slug.'s_flat', $slug.'s', $slug.'_id');

			$column_names = $feature_custom_fields_model::whereIn('id', $ids)->get();

			$column_names_arranged = [];

			foreach($column_names as $column){
				$column_names_arranged[] = $column->label;
			}

			$flat_table->dropColumns($column_names_arranged);
			$column_names_arranged = null;
			

			$feature_custom_fields_model::whereIn('id', $ids)->delete();
			
			$this->modifyArrangedFieldsSettings($type, $company_id, $custom_id, $feature_custom_fields_model);

			return response(['message' => 'Custom field(s) deleted successfully', 'validity' => 'delete_success'], 200);

		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], 500);
		}

	}

}