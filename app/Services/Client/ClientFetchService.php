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
use App\Modules\EasyIndex\EasyIndex;
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
		private ClientRepository $client_repository,
		private EasyIndex $easy_index
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
	 * fetchIndex function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchIndex(Request $request) : array {

		$joins = [
					[
						'table' => 'clients_flat',
						'first' => 'clients.id',
						'operator' => '=',
						'second' => 'clients_flat.client_id',
						'columns' => '' //this will be replaced by EasyIndex class.
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

			$default_columns = [
				'searchable_columns'	=>	['clients.first_name', 'clients.last_name', 'clients.email'],
				'searchable_dates'		=>	['clients.created_at'],
				'show_columns'			=>	[
					[
						'label'	=>	'first_name',
						'text'	=>	'First name',
					],
					[
						'label'	=>	'last_name',
						'text'	=>	'Last name',
					],
					[
						'label'	=>	'email',
	 					'text'	=>	'Email',
					],
					[
						'label'	=>	'created_at',
	 					'text'	=>	'Added on',
					]
				],
			];

			$rewrites = [
				'data' => [
					'clients.send_reminders' => [
						0	=>	'No',
						1	=>	"Yes"
					],
					'clients.e_invoice_enabled' => [
						0	=>	'No',
						1	=>	"Yes"
					]
				],

				'ui'	=>	[
					'send_reminders'	=>	[
						[
							'type'			=>	'label',
							'highlight'		=>	'error',
							'text'			=>	'No',
							'value'			=>	0,
						],
						[
							'type'			=>	'label',
							'highlight'		=>	'success',
							'text'			=>	'Yes',
							'value'			=>	1,
						]
					],
					'e_invoice_enabled'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	'No',
							'value'		=>	0,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	'Yes',
							'value'		=>	1
						]
					]
				]
			];


		return $this->easy_index->setType('client')->setCustomFieldClass(ClientsCustomField::class)->setJoins($joins)->setExceptionClass(ClientException::class)->setRequest($request)->setDefaultColumns($default_columns)->setRewrites($rewrites)->setModel(Client::class)->fetchIndex();
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