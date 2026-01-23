<?php

namespace App\Modules\CustomFieldsFeature\Crud;


use App\Modules\CustomFieldsFeature\Exceptions\RecordNotFoundException;
use App\Modules\DataTable\DataTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Read class
 */
class Read{

	public function __construct(private DataTable $datatable){
		
	}

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

	/**
	 * indexData function
	 *
	 * @param array $data
	 * @param string $feature_custom_fields_model
	 * @param string $slug
	 * @return array
	 */
	public function indexData(array $data, string $feature_custom_fields_model, string $slug) : array {
		
		$company_id = $data['company_id'];

		$fields = $this->datatable
		->setVars($data)
		->setModel($feature_custom_fields_model)
		->skipColumns(['deleted_at', 'updated_at'])
		->setCompanyId($company_id)
		->setJoins([[
			'table' => 'custom_field_types',
			'first' => $slug.'s_custom_fields.custom_field_type_id',
			'operator' => '=',
			'second' => 'custom_field_types.id',
			'columns' => ['custom_field_types.input_type as input_type']
		]])
		->setDatesColumns([$slug.'s_custom_fields.created_at'])
		->setSearchableColumns(['*'])
		->setRewrites([
			$slug.'s_custom_fields.required' => [
				0	=>	'No',
				1	=>	"Yes"
			]
		])
		->results();
		
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

	/**
	 * showData function
	 *
	 * @param string $feature_custom_fields_model
	 * @param integer $company_id
	 * @param integer $id
	 * @return Model
	 */
	public function showData(string $feature_custom_fields_model, int $company_id, int $id) : Model {
		
		$custom_field = $feature_custom_fields_model::select('custom_field_type_id', 'label', 'placeholder', 'required', 'default_value', 'order_on_add_edit_page', 'type_params')->where([['id', '=', $id], ['company_id','=', $company_id]])->with('customFieldType')->first();

		if(!$custom_field){
			throw new RecordNotFoundException("record not found");
		}

		return $custom_field;

	}

}