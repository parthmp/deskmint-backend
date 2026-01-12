<?php

namespace App\Services\FieldType;

use App\Models\CustomFieldType;
use App\Modules\DataTable\DataTable;
use App\Repositories\CustomFieldType\CustomFieldTypeRepository;
use Exception;

/**
 * EmailSettingsSMTPService class
 */
class FieldTypesService{

	public function __construct(private CustomFieldTypeRepository $custom_field_type_repository, private DataTable $datatable){}

	/**
	 * getInputTypes function
	 *
	 * @return array
	 */
	public function fetchInputTypes() : array {

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

	/**
	 * create function
	 *
	 * @param string $input_type
	 * @param string $input_name
	 * @return boolean
	 */
	public function create(string $input_type, string $input_name) : bool {

		return $this->custom_field_type_repository->create($input_name, $input_name);

	}

	/**
	 * fetch function
	 *
	 * @param array $data
	 * @return array
	 */
	public function fetch(array $data) : array {

		$fields = $this->datatable
		->setVars($data)
		->setModel(CustomFieldType::class)
		->skipColumns(['deleted_at', 'updated_at'])
		->setDatesColumns(['created_at'])
		->setSearchableColumns(['*'])
		->results();

		$fields->each(function($ele){
			$ele->input_type = ucfirst($ele->input_type);
		});
		
		$table_data = [
			'columns' => [
				[
					'label' => 	'id',
					'text'	=>	'ID#'
				],
				[
					'label' => 	'input_type',
					'text'	=>	'Input type'
				],
				[
					'label'	=>	'input_name',
					'text'	=>	'Input name'
				],
				[
					'label'	=>	'created_at',
					'text'	=>	'Added on'
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
			'total_pages'	=>		$total_pages,
			'current_page'	=>		$fields->currentPage()
		];

	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return CustomFieldType|null
	 */
	public function fetchById(int $id) : ?CustomFieldType {

		$field = $this->custom_field_type_repository->fetchById($id);
		if(!$field){
			throw new Exception('unable to find the field');
		}

		return $field;

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
		return $this->custom_field_type_repository->updateByObj($input_type, $input_name, $obj);
	}

}