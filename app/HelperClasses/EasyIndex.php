<?php

namespace App\HelperClasses;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Modules\ArrangedDataTableColumns\DatabaseOperations\DatabaseOperations as ArrangedDBperations;
use App\Modules\CustomFields\CustomFields;
use App\Modules\DataTable\DataTable;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class EasyIndex{

	private string $type;
	private string $custom_fields_class;
	private array $joins;
	private string $exception_class;
	private Request $request;
	private array $default_columns = [
		'searchable_columns'	=>	[],
		'searchable_dates'		=>	[],
		'show columns'			=>	[], //must have label and text for each element
	];

	private string $model;

	public function __construct(private ArrangedDBperations $arranged_db_operations, private CustomFields $custom_fields, private DataTable $datatable){}
	
	/**
	 * setType function
	 *
	 * @param string $type
	 * @return self
	 */
	public function setType(string $type) : self {
		$this->type = $type;
		return $this;
	}

	/**
	 * setCustomFieldClass function
	 *
	 * @param string $custom_fields_class
	 * @return self
	 */
	public function setCustomFieldClass(string $custom_fields_class) : self {
		$this->custom_fields_class = $custom_fields_class;
		return $this;
	}

	/**
	 * setJoins function
	 *
	 * @param array $joins
	 * @return self
	 */
	public function setJoins(array $joins) : self {
		$this->joins = $joins;
		return $this;
	}

	/**
	 * getJoins function
	 *
	 * @param array $clients_flat_columns
	 * @return array
	 */
	private function getJoins(array $clients_flat_columns) : array {
		$this->joins[0]['columns'] = $clients_flat_columns;
		return $this->joins;
	}

	/**
	 * setExceptionClass function
	 *
	 * @param string $exception_class
	 * @return self
	 */
	public function setExceptionClass(string $exception_class) : self {
		$this->exception_class = $exception_class;
		return $this;
	}

	/**
	 * setRequest function
	 *
	 * @param Request $request
	 * @return self
	 */
	public function setRequest(Request $request) : self{
		$this->$request = $request;
		return $this;
	}

	/**
	 * setDefaultColumns function
	 *
	 * @param array $default_columns
	 * @return self
	 */
	public function setDefaultColumns(array $default_columns) : self {
		$this->$default_columns = $default_columns;
		return $this;
	}

	/**
	 * setModel function
	 *
	 * @param string $model
	 * @return self
	 */
	public function setModel(string $model) : self {
		$this->model = $model;
		return $this;
	}

	/**
	 * processTempLabel function
	 *
	 * @param string $temp_label
	 * @return string
	 */
	private function processTempLabel(string $temp_label) : string {

		/* handle edge cases here */

		return match($temp_label){
			'company_id'				=>		'company_name',
			'currency_id'				=>		'currency',
			'billing_country_id'		=>		'b_country_name',
			'shipping_country_id'		=>		's_country_name',
			'industry_id'				=>		'industry_name',
			default						=>		$temp_label
		};

	}

	private function processCustomColumns(array $custom_columns, int $clients_custom_fields_id, string $type) : array {

		$show_columns = [];

		for($x = 0 ; $x < count($custom_columns) ; $x++){

			if($clients_custom_fields_id === (int) $custom_columns[$x]['id']){

				$label_with_underscores = General::replaceWithUnderscores($custom_columns[$x]['label']);

				if($custom_columns[$x]['custom_field_type']['input_type'] === config('global.field_types')[5]){
					$date_only_columns[] = $label_with_underscores;
				}

				$clients_flat_columns[] = $type.'s_flat.'.$label_with_underscores.' as '.$label_with_underscores;
				$show_columns[] = [
					'label'	=>	General::replaceWithUnderscores($custom_columns[$x]['label']),
					'text'	=>	General::NormalizeColumnName($custom_columns[$x]['label'])
				];

				

			}

		}

		return $show_columns;

	}

	private function processTempLabelForSearchables(string $temp_label, string $default_label, string $type) : string {

		return match($temp_label){
			'company_id'				=>		'companies.company_name',
			'currency_id'				=>		'currencies.currency',
			'billing_country_id'		=>		'b_countries.country_name',
			'shipping_country_id'		=>		's_countries.country_name',
			'industry_id'				=>		'industries.industry_name',
			default						=>		$type.'s.'.$default_label
		};

	}

	// private function getJoins(array $clients_flat_columns) : array {
	// 	return [
	// 				[
	// 					'table' => 'clients_flat',
	// 					'first' => 'clients.id',
	// 					'operator' => '=',
	// 					'second' => 'clients_flat.client_id',
	// 					'columns' => $clients_flat_columns
	// 				],
	// 				[
	// 					'table' => 'companies',
	// 					'first' => 'clients.company_id',
	// 					'operator' => '=',
	// 					'second' => 'companies.id',
	// 					'columns' => ['companies.company_name as company_name']
	// 				],
	// 				[
	// 					'table' => 'currencies',
	// 					'first' => 'clients.currency_id',
	// 					'operator' => '=',
	// 					'second' => 'currencies.id',
	// 					'columns' => ['currencies.currency as currency']
	// 				],
	// 				[
	// 					'table' => 'countries as b_countries',
	// 					'first' => 'clients.billing_country_id',
	// 					'operator' => '=',
	// 					'second' => 'b_countries.id',
	// 					'columns' => ['b_countries.country_name as b_country_name']
	// 				],
	// 				[
	// 					'table' => 'countries as s_countries',
	// 					'first' => 'clients.shipping_country_id',
	// 					'operator' => '=',
	// 					'second' => 's_countries.id',
	// 					'columns' => ['s_countries.country_name as s_country_name']
	// 				],
	// 				[
	// 					'table' => 'industries',
	// 					'first' => 'clients.industry_id',
	// 					'operator' => '=',
	// 					'second' => 'industries.id',
	// 					'columns' => ['industries.industry_name as industry_name']
	// 				]
	// 			];

	// }

	private function processDataTable(array $clients_flat_columns, array $data, array $searchable_dates, array $searchable_columns, int $company_id, array $joins) : LengthAwarePaginator {
		
		$joins = $this->getJoins($clients_flat_columns);

		$fields = $this->datatable->setVars($data)->setModel($this->model)->skipColumns(['deleted_at', 'updated_at'])->setDatesColumns($searchable_dates)->setCompanyId($company_id)->setJoins($joins)->setSearchableColumns($searchable_columns)->setRewrites([
			'clients.send_reminders' => [
				0	=>	'No',
				1	=>	"Yes"
			],
			'clients.e_invoice_enabled' => [
				0	=>	'No',
				1	=>	"Yes"
			]
		])->results();
		
		$fields->each(function($ele){
			
			if((int)$ele->send_reminders === 0){
				$ele->send_reminders = [
					'type'		=>	'label',
					'highlight'	=>	'error',
					'text'		=>	'No'
				];
			}else{
				$ele->send_reminders = [
					'type'		=>	'label',
					'highlight'	=>	'success',
					'text'		=>	'Yes'
				];
			}

			if((int)$ele->e_invoice_enabled === 0){
				$ele->e_invoice_enabled = [
					'type'		=>	'label',
					'highlight'	=>	'error',
					'text'		=>	'No'
				];
			}else{
				$ele->e_invoice_enabled = [
					'type'		=>	'label',
					'highlight'	=>	'success',
					'text'		=>	'Yes'
				];
			}

		});

		return $fields;
	}

	/**
	 * validateForIndex function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	private function validateForIndex(Request $request) : bool {

		$v = Validator::make($request->all(), [
			'default_per_page'	=>	'required|integer|min:1'
		]);

		return !$v->fails();

	}


	public function fetchIndex() : array {

		$validated = $this->validateForIndex($this->request);

		if(!$validated){
			throw new $this->exception_class("Invalid request", 'invalid_request', config('global.error_code'));
		}

		$company_id = Sanitize::input($this->request->input('company_id'));

		/* check custom fields showing fallback */
		$user_data = $this->arranged_db_operations->fetchUserIndexColumnDataByUserId($company_id, $this->type.'s');
		
		if(!$user_data){
			$user_data = $this->arranged_db_operations->fetchSettingsIndexColumnDataByFeatureName($company_id, $this->type.'s');
		}

		$searchable_columns = [];
		$show_columns = [];
		$searchable_dates = [];
		$flat_columns = [];
		$date_only_columns = [];

		if($user_data){
			
			$user_data =  json_decode($user_data->columns_json, true);
			$clients_custom_columns = $this->custom_fields->fetchCustomFieldsArray($this->custom_fields_class, $company_id); /*ClientsCustomField::class*/

			for($z = 0 ; $z < count($user_data) ; $z++){
				$temp_label2 = $user_data[$z]['label'];
				if($user_data[$z]['show'] === true){
					if($user_data[$z]['type'] === 'normal'){

						$temp_label = $this->processTempLabel($user_data[$z]['label']);

						$show_columns[] = [
							'label'	=>	$temp_label,
							'text'	=>	$user_data[$z]['text']
						];
						
						
					}else{
						$show_columns = array_merge($show_columns, $this->processCustomColumns($clients_custom_columns, $user_data[$z][$this->type.'s_custom_fields_id'], $this->type));
					}
				}

				if($user_data[$z]['type'] === 'normal'){
					if($user_data[$z]['searchable'] === true){
						if($user_data[$z]['is_date'] === true){
							$searchable_dates[] = 'clients.'.$user_data[$z]['label'];
						}else{

							$searchable_columns[] = $this->processTempLabelForSearchables($temp_label2, $user_data[$z]['label'], $this->type);
							
						}
						
					}
				}else{
					if($user_data[$z]['searchable'] === true){

						for($x = 0 ; $x < count($clients_custom_columns) ; $x++){

							if($user_data[$z][$this->type.'s_custom_fields_id'] === $clients_custom_columns[$x]['id']){

								$label_with_underscores = General::replaceWithUnderscores($clients_custom_columns[$x]['label']);

								$flat_columns[] = $this->type.'s_flat.'.$label_with_underscores.' as '.$label_with_underscores;

								if($user_data[$z]['is_date'] === true){
									$searchable_dates[] = $this->type.'s_flat.'.$label_with_underscores;
								}else{
									$searchable_columns[] = $this->type.'s_flat.'.$label_with_underscores;
								}
								
							}

						}

						
					}
				}

			}


		}else{

			foreach($this->default_columns['searchable_columns'] as $searchable_column){
				array_push($searchable_columns, $searchable_column);
			}

			foreach($this->default_columns['searchable_dates'] as $searchable_date){
				array_push($searchable_dates, $searchable_date);
			}

			foreach($this->default_columns['show_columns'] as $show_column){
				array_push($show_column, [
					'label'	=>	$show_column['label'],
					'text'	=>	$show_column['text'],
				]);
			}

			// array_push($searchable_columns, 'clients.first_name');
			// array_push($searchable_columns, 'clients.last_name');
			// array_push($searchable_columns, 'clients.email');
			// array_push($searchable_dates, 'clients.created_at');

			// array_push($show_columns, [
			// 	'label'	=>	'first_name',
			// 	'text'	=>	'First name',
			// ]);
			// array_push($show_columns, [
			// 	'label'	=>	'last_name',
			// 	'text'	=>	'Last name',
			// ]);
			// array_push($show_columns, [
			// 	'label'	=>	'email',
			// 	'text'	=>	'Email',
			// ]);
			// array_push($show_columns, [
			// 	'label'	=>	'created_at',
			// 	'text'	=>	'Added on',
			// ]);

		}
		
		$flat_columns = array_unique($flat_columns);
		
		$data['searched_term'] = Sanitize::input($this->request->input('searched_term'));
		$data['current_page'] = Sanitize::input($this->request->input('current_page'));
		$data['sorted_column'] = $this->request->input('sorted_column');
		$data['default_per_page'] = Sanitize::input($this->request->input('default_per_page'));
		$data['per_page'] = $this->request->input('per_page') ? Sanitize::input($this->request->input('per_page')) : $data['default_per_page'];
		$data['date_range'] = $this->request->input('date_range');
		
		$fields = $this->processDataTable($flat_columns, $data, $searchable_dates, $searchable_columns, $company_id, $this->joins);
		
		$rows = $fields->items();
		
		for($z = 0 ; $z < count($rows) ; $z++){
			
			foreach($rows[$z]->getAttributes() as $col_key => $col_val){
				
				if(!is_array($col_val) && General::isMySQLDateTime($col_val)){
					
					if(in_array($col_key, $date_only_columns)){
						$rows[$z]->{$col_key} = [
							'type' 	=> 'date',
							'text'	=>	Carbon::parse($col_val)->toISOString()
						];
					}else{
						$rows[$z]->{$col_key} = Carbon::parse($col_val)->toISOString();
					}

				}

				
			}
		 	
		}
		array_push($show_columns, [
			'label'	=>	'actions',
			'text'	=>	'Actions'
		]);
		$table_data = [
			'columns' => $show_columns,
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