<?php

namespace App\Services\Client;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Client;
use App\Models\ClientCustomFieldValue;
use App\Models\ClientsCustomField;
use App\Modules\ArrangedDataTableColumns\DatabaseOperations\DatabaseOperations as ArrangedDataTableDatabaseOperations;
use App\Modules\CustomFields\CustomFields;
use App\Modules\DataTable\DataTable;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Currency\CurrencyRepository;
use App\Repositories\Industry\IndustryRepository;
use App\Services\Client\Exceptions\ClientException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ClientFetchService class
 */
class ClientFetchService{

	
	public function __construct(
		private CustomFields $custom_fields, 
		private CurrencyRepository $currency_repository, 
		private IndustryRepository $industry_repository, 
		private ClientValidationService $client_validation_service,
		private ArrangedDataTableDatabaseOperations $arranged_database_operations,
		private DataTable $datatable,
		private ClientRepository $client_repository
	){}
	
	/**
	 * fetchCustomFields function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchCustomFields(Request $request) : array {

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = $this->custom_fields->fetchCustomFields(ClientsCustomField::class, $company_id);

		$currencies = $this->currency_repository->fetchWithTextAndValue();

		$industries = $this->industry_repository->fetchWithTextAndValue();


		return [
					'data_fields' 	=>  $this->custom_fields->printCustomFields($fields),
					'countries'		=>	General::fetchCoutries(),
					'currencies'	=>	$currencies,
					'industries'	=>	$industries,
				];

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

	/**
	 * processTempLabelForSearchables function
	 *
	 * @param string $temp_label
	 * @param string $default_label
	 * @return string
	 */
	private function processTempLabelForSearchables(string $temp_label, string $default_label) : string {

		return match($temp_label){
			'company_id'				=>		'companies.company_name',
			'currency_id'				=>		'currencies.currency',
			'billing_country_id'		=>		'b_countries.country_name',
			'shipping_country_id'		=>		's_countries.country_name',
			'industry_id'				=>		'industries.industry_name',
			default						=>		'clients.'.$default_label
		};

	}

	/**
	 * processClientCustomColumns function
	 *
	 * @param array $clients_custom_columns
	 * @param integer $clients_custom_fields_id
	 * @return array
	 */
	private function processClientCustomColumns(array $clients_custom_columns, int $clients_custom_fields_id) : array {

		$show_columns = [];

		for($x = 0 ; $x < count($clients_custom_columns) ; $x++){

			if($clients_custom_fields_id === (int) $clients_custom_columns[$x]['id']){

				$label_with_underscores = General::replaceWithUnderscores($clients_custom_columns[$x]['label']);

				if($clients_custom_columns[$x]['custom_field_type']['input_type'] === config('global.field_types')[5]){
					$date_only_columns[] = $label_with_underscores;
				}

				$clients_flat_columns[] = 'clients_flat.'.$label_with_underscores.' as '.$label_with_underscores;
				$show_columns[] = [
					'label'	=>	General::replaceWithUnderscores($clients_custom_columns[$x]['label']),
					'text'	=>	General::NormalizeColumnName($clients_custom_columns[$x]['label'])
				];

				

			}

		}

		return $show_columns;

	}

	private function getJoins(array $clients_flat_columns) : array {
		return [
					[
						'table' => 'clients_flat',
						'first' => 'clients.id',
						'operator' => '=',
						'second' => 'clients_flat.client_id',
						'columns' => $clients_flat_columns
					],
					[
						'table' => 'companies',
						'first' => 'clients.company_id',
						'operator' => '=',
						'second' => 'companies.id',
						'columns' => ['companies.company_name as company_name']
					],
					[
						'table' => 'currencies',
						'first' => 'clients.currency_id',
						'operator' => '=',
						'second' => 'currencies.id',
						'columns' => ['currencies.currency as currency']
					],
					[
						'table' => 'countries as b_countries',
						'first' => 'clients.billing_country_id',
						'operator' => '=',
						'second' => 'b_countries.id',
						'columns' => ['b_countries.country_name as b_country_name']
					],
					[
						'table' => 'countries as s_countries',
						'first' => 'clients.shipping_country_id',
						'operator' => '=',
						'second' => 's_countries.id',
						'columns' => ['s_countries.country_name as s_country_name']
					],
					[
						'table' => 'industries',
						'first' => 'clients.industry_id',
						'operator' => '=',
						'second' => 'industries.id',
						'columns' => ['industries.industry_name as industry_name']
					]
				];

	}

	private function processDataTable(array $clients_flat_columns, array $data, array $searchable_dates, array $searchable_columns, int $company_id) : LengthAwarePaginator {
		
		$joins = $this->getJoins($clients_flat_columns);
		
		$fields = $this->datatable->setVars($data)->setModel(Client::class)->skipColumns(['deleted_at', 'updated_at'])->setDatesColumns($searchable_dates)->setCompanyId($company_id)->setJoins($joins)->setSearchableColumns($searchable_columns)->setRewrites([
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

	public function fetchIndex(Request $request) : array {

		$validated = $this->client_validation_service->validateForIndex($request);

		if(!$validated){
			throw new ClientException("Invalid request", 'invalid_request', config('global.error_code'));
		}

		$company_id = Sanitize::input($request->input('company_id'));

		/* check custom fields showing fallback */
		$user_data = $this->arranged_database_operations->fetchUserIndexColumnDataByUserId($company_id, 'clients');
		
		if(!$user_data){
			$user_data = $this->arranged_database_operations->fetchSettingsIndexColumnDataByFeatureName($company_id, 'clients');
		}

		$searchable_columns = [];
		$show_columns = [];
		$searchable_dates = [];
		$clients_flat_columns = [];
		$date_only_columns = [];

		if($user_data){
			
			$user_data =  json_decode($user_data->columns_json, true);
			$clients_custom_columns = $this->custom_fields->fetchCustomFieldsArray(ClientsCustomField::class, $company_id);

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
						$show_columns = array_merge($show_columns, $this->processClientCustomColumns($clients_custom_columns, $user_data[$z]['clients_custom_fields_id']));
					}
				}

				if($user_data[$z]['type'] === 'normal'){
					if($user_data[$z]['searchable'] === true){
						if($user_data[$z]['is_date'] === true){
							$searchable_dates[] = 'clients.'.$user_data[$z]['label'];
						}else{

							$searchable_columns[] = $this->processTempLabelForSearchables($temp_label2, $user_data[$z]['label']);
							
						}
						
					}
				}else{
					if($user_data[$z]['searchable'] === true){

						for($x = 0 ; $x < count($clients_custom_columns) ; $x++){

							if($user_data[$z]['clients_custom_fields_id'] === $clients_custom_columns[$x]['id']){

								$label_with_underscores = General::replaceWithUnderscores($clients_custom_columns[$x]['label']);

								$clients_flat_columns[] = 'clients_flat.'.$label_with_underscores.' as '.$label_with_underscores;

								if($user_data[$z]['is_date'] === true){
									$searchable_dates[] = 'clients_flat.'.$label_with_underscores;
								}else{
									$searchable_columns[] = 'clients_flat.'.$label_with_underscores;
								}
								
							}

						}

						
					}
				}

			}


		}else{

			array_push($searchable_columns, 'clients.first_name');
			array_push($searchable_columns, 'clients.last_name');
			array_push($searchable_columns, 'clients.email');
			array_push($searchable_dates, 'clients.created_at');

			array_push($show_columns, [
				'label'	=>	'first_name',
				'text'	=>	'First name',
			]);
			array_push($show_columns, [
				'label'	=>	'last_name',
				'text'	=>	'Last name',
			]);
			array_push($show_columns, [
				'label'	=>	'email',
				'text'	=>	'Email',
			]);
			array_push($show_columns, [
				'label'	=>	'created_at',
				'text'	=>	'Added on',
			]);

		}
		
		$clients_flat_columns = array_unique($clients_flat_columns);
		
		$data['searched_term'] = Sanitize::input($request->input('searched_term'));
		$data['current_page'] = Sanitize::input($request->input('current_page'));
		$data['sorted_column'] = $request->input('sorted_column');
		$data['default_per_page'] = Sanitize::input($request->input('default_per_page'));
		$data['per_page'] = $request->input('per_page') ? Sanitize::input($request->input('per_page')) : $data['default_per_page'];
		$data['date_range'] = $request->input('date_range');
		
		$fields = $this->processDataTable($clients_flat_columns, $data, $searchable_dates, $searchable_columns, $company_id);
		
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

	
	/**
	 * fetchSingleClientById function
	 *
	 * @param integer $id
	 * @return array
	 */
	public function fetchSingleClientById(int $id) : array {

		$id = Sanitize::input($id);

		$client = $this->client_repository->fetchAllDataById($id);

		if(!$client){
			throw new ClientException('Invalid request', 'invalid_request', config('global.error_code'));
		}

		$custom_fields = $this->custom_fields->fetchCustomFieldValues($id, 'client', ClientCustomFieldValue::class);

		$contact_info = $this->client_repository->fetchClientContactInfoById($id);

		return ['client_info' => $client, 'contact_info' => $contact_info, 'custom_fields' => $custom_fields];

	}

}