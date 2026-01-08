<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Client;
use App\Models\ClientContactInfo;
use App\Models\ClientCustomFieldValue;
use App\Models\ClientsCustomField;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\SettingsIndexColumn;
use App\Models\UserIndexColumn;
use App\Modules\DataTable\DataTable;
//use App\Services\DataTable;
use App\Traits\ArrangedColumns;
use App\Traits\CustomFieldsPrinting;
use App\Traits\CustomFieldsUpsert;
use App\Traits\CustomFieldsValidation;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ClientsController extends Controller{

	use CustomFieldsPrinting, CustomFieldsValidation, CustomFieldsUpsert, ArrangedColumns;

	public function __construct(private DataTable $datatable){}

	public function fetchClientsCustomFields(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();

		$currencies = Currency::orderBy('currency', 'asc')->get()->map(function($currency){
			return [
				'value'	=>	$currency->id,
				'text'	=>	$currency->currency.' - '.$currency->code
			];
		});

		$industries = Industry::orderBy('industry_name', 'asc')->get()->map(function($ind){
			return [
				'value'	=>	$ind->id,
				'text'	=>	$ind->industry_name
			];
		});

		return [
					'data_fields' 	=> $this->adjustRowsPrinting($fields),
					'countries'		=>	General::fetchCoutries(),
					'currencies'	=>	$currencies,
					'industries'	=>	$industries,
				];

	}

	public function store(Request $request){
		return $this->saveOrUpdateClient($request, true);
	}

	private function upsertContactInfoForClient(Request $request, int $client_id, bool $add = true){

		$contact_info = $request->input('contact_info');

		$upsert = [];

		foreach($contact_info as $info){

			$info_id = Sanitize::input($info['id']);
			$first_name = Sanitize::input($info['first_name']['value']);
			$last_name = Sanitize::input($info['last_name']['value']);
			$email = Sanitize::input($info['email']['value']);
			$phone = Sanitize::input($info['phone']['value'].'');

			$temp_upsert = [];

			if(!$add){
				$temp_upsert['id'] = $info_id;
			}
			$temp_upsert['client_id'] = $client_id;
			$temp_upsert['first_name'] = $first_name;
			$temp_upsert['last_name'] = $last_name;
			$temp_upsert['email'] = $email;
			$temp_upsert['phone'] = $phone;
			$temp_upsert['deleted_at'] = null;

			$upsert[] = $temp_upsert;

		}

		if(!empty($upsert)){
			ClientContactInfo::upsert($upsert, ['id'], ['first_name', 'last_name', 'email', 'phone', 'deleted_at']);
		}

	}

	private function validatePersonInfo(Request $request){

		$validation_rules1 = [
			'personal_info.first_name.value'	=>	'required',
			'personal_info.last_name.value'		=>	'required',
			'personal_info.email.value'			=>	'required|email'
		];

		$personal_info_validation = Validator::make($request->all(), $validation_rules1);
		if($personal_info_validation->fails()){
			return response(['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab1', 'tab_switch' => 0], config('global.error_code'));
		}

		return null;
	}

	private function validateContactInfo(Request $request){

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab2', 'tab_switch' => 1];

		if(!$request->has('contact_info')){
			return response($response, config('global.error_code'));
		}

		$contact_info = $request->input('contact_info');
		if(empty($contact_info)){
			$response['message'] = 'Please have at least one contact info added';
			return response($response, config('global.error_code'));
		}

		$validation_rules2 = [
			'contact_info'							=>	'required|array|min:1',
			'contact_info.*.id'						=>	'required',
			'contact_info.*.first_name.value'		=>	'required',
			'contact_info.*.last_name.value'		=>	'required',
			'contact_info.*.email.value'			=>	'required|email',
		];

		$contact_info_validation = Validator::make($request->all(), $validation_rules2);

		if($contact_info_validation->fails()){
			return response($response, config('global.error_code'));
		}

		return null;
	}

	private function validateBillingNShippingInfo(Request $request, bool $copy_to_shipping){

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab3', 'tab_switch' => 2];

		if(!$request->has('billing_info')){
			return response($response, config('global.error_code'));
		}

		$validation_rules3 = [
			'billing_info.street.value'						=>	'required',
			'billing_info.apt.value'						=>	'required',
			'billing_info.city.value'						=>	'required',
			'billing_info.state.value'						=>	'required',
			'billing_info.postal_code.value'				=>	'required',
			'billing_info.country.value'					=>	'required|exists:countries,id',
		];

		$billing_info_validation = Validator::make($request->all(), $validation_rules3);
		if($billing_info_validation->fails()){
			return response($response, config('global.error_code'));
		}

		if($copy_to_shipping === false){

			$validation_rules3 = [
				'shipping_info.street.value'						=>	'required',
				'shipping_info.apt.value'							=>	'required',
				'shipping_info.city.value'							=>	'required',
				'shipping_info.state.value'							=>	'required',
				'shipping_info.postal_code.value'					=>	'required',
				'shipping_info.country.value'						=>	'required|exists:countries,id',
			];

			$shipping_info_validation = Validator::make($request->all(), $validation_rules3);
			if($shipping_info_validation->fails()){
				return response($response, config('global.error_code'));
			}

		}
		
		return null;

	}

	private function validateSettings(Request $request){

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab5', 'tab_switch' => 4];

		if(!$request->has('settings')){
			return response($response, config('global.error_code'));
		}

		$settings_rules = [
			'settings.currency.value'								=>	'required|exists:currencies,id',
			'settings.industry.value'								=>	'required|exists:industries,id',
			'settings.payment_terms.value'							=>	'required|in:0,7,14,30,60,90',
			'settings.quote_valid.value'							=>	'required|in:0,7,14,30,60,90',
			'settings.send_reminder.value'							=>	'required|in:0,1',
			'settings.size.value'									=>	'required',
		];

		$settings_validation = Validator::make($request->all(), $settings_rules);
		if($settings_validation->fails()){
			return response($response, config('global.error_code'));
		}

		return null;

	}

	public function index(Request $request){

		$v = Validator::make($request->all(), [
			'default_per_page'	=>	'required|integer|min:1'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}
		
		$company_id = Sanitize::input($request->input('company_id'));

		/* check custom fields showing fallback */
		$user_data = UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();
		
		if(!$user_data){
			$user_data = SettingsIndexColumn::where([['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();
		}

		$searchable_columns = [];
		$show_columns = [];
		$searchable_dates = [];
		$clients_flat_columns = [];
		$date_only_columns = [];

		if($user_data){
			
			$user_data =  json_decode($user_data->columns_json, true);
			$clients_custom_columns = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->with('customFieldType')->get()->toArray();

			for($z = 0 ; $z < count($user_data) ; $z++){
				$temp_label2 = $user_data[$z]['label'];
				if($user_data[$z]['show'] === true){
					if($user_data[$z]['type'] === 'normal'){

						$temp_label = $user_data[$z]['label'];
						

						/* handle edge cases here */
						if($temp_label === 'company_id'){
							$temp_label = 'company_name';
						}else if($temp_label === 'currency_id'){
							$temp_label = 'currency';
						}else if($temp_label === 'billing_country_id'){
							$temp_label = 'b_country_name';
						}else if($temp_label === 'shipping_country_id'){
							$temp_label = 's_country_name';
						}else if($temp_label === 'industry_id'){
							$temp_label = 'industry_name';
						}

						$show_columns[] = [
							'label'	=>	$temp_label,
							'text'	=>	$user_data[$z]['text']
						];
						
						
					}else{

						for($x = 0 ; $x < count($clients_custom_columns) ; $x++){

							if($user_data[$z]['clients_custom_fields_id'] === $clients_custom_columns[$x]['id']){

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

					}
				}

				/* refactor this later on if possible */
				if($user_data[$z]['type'] === 'normal'){
					if($user_data[$z]['searchable'] === true){
						if($user_data[$z]['is_date'] === true){
							$searchable_dates[] = 'clients.'.$user_data[$z]['label'];
						}else{

							if($temp_label2 === 'company_id'){
								$searchable_columns[] = 'companies.company_name';
							}else if($temp_label2 === 'currency_id'){
								$searchable_columns[] = 'currencies.currency';
							}else if($temp_label2 === 'billing_country_id'){
								$searchable_columns[] = 'b_countries.country_name';
							}else if($temp_label2 === 'shipping_country_id'){
								$searchable_columns[] = 's_countries.country_name';
							}else if($temp_label2 === 'industry_id'){
								$searchable_columns[] = 'industries.industry_name';
							}else{
								$searchable_columns[] = 'clients.'.$user_data[$z]['label'];
							}

							
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
		$data['per_page'] = Sanitize::input($request->input('default_per_page'));
		$data['date_range'] = $request->input('date_range');

		$joins =	[
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

		$fields = $this->datatable->setVars($data)->setModel(Client::class)->skipColumns(['deleted_at', 'updated_at'])->setDatesColumns($searchable_dates)->setCompanyId($company_id)->setJoins($joins)->setSearchableColumns($searchable_columns)->results();
		
		// $fields = DataTable::sortNPaginate(
		// 	$request,
		// 	\App\Models\Client::class,
		// 	['deleted_at', 'updated_at'],
		// 	$company_id,
		// 	$searchable_dates,
		// 	[
		// 		[
		// 			'table' => 'clients_flat',
		// 			'first' => 'clients.id',
		// 			'operator' => '=',
		// 			'second' => 'clients_flat.client_id',
		// 			'columns' => $clients_flat_columns
		// 		],
		// 		[
		// 			'table' => 'companies',
		// 			'first' => 'clients.company_id',
		// 			'operator' => '=',
		// 			'second' => 'companies.id',
		// 			'columns' => ['companies.company_name as company_name']
		// 		],
		// 		[
		// 			'table' => 'currencies',
		// 			'first' => 'clients.currency_id',
		// 			'operator' => '=',
		// 			'second' => 'currencies.id',
		// 			'columns' => ['currencies.currency as currency']
		// 		],
		// 		[
		// 			'table' => 'countries as b_countries',
		// 			'first' => 'clients.billing_country_id',
		// 			'operator' => '=',
		// 			'second' => 'b_countries.id',
		// 			'columns' => ['b_countries.country_name as b_country_name']
		// 		],
		// 		[
		// 			'table' => 'countries as s_countries',
		// 			'first' => 'clients.shipping_country_id',
		// 			'operator' => '=',
		// 			'second' => 's_countries.id',
		// 			'columns' => ['s_countries.country_name as s_country_name']
		// 		],
		// 		[
		// 			'table' => 'industries',
		// 			'first' => 'clients.industry_id',
		// 			'operator' => '=',
		// 			'second' => 'industries.id',
		// 			'columns' => ['industries.industry_name as industry_name']
		// 		]
		// 	],
		// 	[],
		// 	$searchable_columns
		// );
		
		$rows = $fields->items();
		
		for($z = 0 ; $z < count($rows) ; $z++){
			
			foreach($rows[$z]->getAttributes() as $col_key => $col_val){
				
				if(General::isMySQLDateTime($col_val)){
					
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

	public function fetchArrangedColumns(Request $request){
		return $this->fetchArrangedColumnsData($request, 'clients', 'clients', ClientsCustomField::class, 'client');
	}
	

	public function saveArrangedColumns(Request $request){
		return $this->saveArrangedColumnsData($request, ClientsCustomField::class, 'clients', 'clients', 'client');
	}

	public function destroy(Request $request){
		
		$ids = $request->input('ids');
		
		if(!is_array($ids) || empty($ids)){
			return response(['message' => 'No valid IDs provided', 'validity' => 'invalid_ids'], config('global.error_code'));
		}

		foreach($ids as $id){
			if(!is_numeric($id)){
				return response(['message' => 'All IDs must be numeric', 'validity' => 'non_numeric'], config('global.error_code'));
			}
		}

		try{

			$flat_table = 'clients_flat';

			if(Schema::hasTable($flat_table)){
				DB::table($flat_table)->whereIn('client_id', $ids)->delete();
			}

			Client::whereIn('id', $ids)->delete();
			return response(['message' => 'Custom field(s) deleted successfully', 'validity' => 'delete_success'], 200);

		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], 500);
		}

	}

	public function show(Request $request){
		
		$id = $request->segment(3);

		$client = Client::where('id', '=', $id)->with('billing_country')->with('shipping_country')->with('currency')->with('industry')->first();
		if(!$client){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$custom_fields = ClientCustomFieldValue::where('client_id', '=', $id)->whereHas('ClientsCustomField')->whereHas('ClientsCustomField.customFieldType')->with('ClientsCustomField', 'ClientsCustomField.customFieldType')->get();

		$contact_info = ClientContactInfo::where('client_id', '=', $id)->get();
		
		return ['client_info' => $client, 'contact_info' => $contact_info, 'custom_fields' => $custom_fields];

	}

	public function update(Request $request){
		
		return $this->saveOrUpdateClient($request, false);

	}

	private function saveOrUpdateClient(Request $request, $add = true){
		
		if(!$add){

			$client_id = Sanitize::input($request->segment(3));
			$client = Client::where('id', '=', $client_id)->first();
			
			if(!$client){
				return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}

		}

		$personal_info_validation = $this->validatePersonInfo($request);
		if($personal_info_validation !== null){
			return $personal_info_validation;
		}

		$contact_info_validation = $this->validateContactInfo($request);
		if($contact_info_validation !== null){
			return $contact_info_validation;
		}

		$copy_to_shipping = false;
		if($request->has('copy_to_shipping')){
			$copy_to_shipping = Sanitize::input($request->input('copy_to_shipping'));
			$copy_to_shipping = filter_var($copy_to_shipping.'', FILTER_VALIDATE_BOOLEAN);
		}

		$billing_and_shipping_validation = $this->validateBillingNShippingInfo($request, $copy_to_shipping);
		if($billing_and_shipping_validation !== null){
			return $billing_and_shipping_validation;
		}

		$custom_fields_validation = $this->validateCustomFields($request, ClientsCustomField::class, 'invalid_data_tab4');
		if($custom_fields_validation !== null){
			return $custom_fields_validation;
		}

		$settings_validation = $this->validateSettings($request);
		if($settings_validation !== null){
			return $settings_validation;
		}
		
		try{

			$company_id = Sanitize::input($request->input('company_id'));
			$personal_info_first_name = Sanitize::input($request->input('personal_info.first_name.value'));
			$personal_info_last_name = Sanitize::input($request->input('personal_info.last_name.value'));
			$personal_info_tax_id = Sanitize::input($request->input('personal_info.tax_id.value').'');
			$website = Sanitize::input($request->input('personal_info.website.value').'');
			$phone = Sanitize::input($request->input('personal_info.phone.value').'');
			$email = Sanitize::input($request->input('personal_info.email.value'));
			$billing_street = Sanitize::input($request->input('billing_info.street.value'));
			$billing_apt = Sanitize::input($request->input('billing_info.apt.value'));
			$billing_city = Sanitize::input($request->input('billing_info.city.value'));
			$billing_state = Sanitize::input($request->input('billing_info.state.value'));
			$billing_postal_code = Sanitize::input($request->input('billing_info.postal_code.value'));
			$billing_country_id = Sanitize::input($request->input('billing_info.country.value'));

			/* init shipping info */
			$shipping_street = Sanitize::input($request->input('shipping_info.street.value').'');
			$shipping_apt = Sanitize::input($request->input('shipping_info.apt.value').'');
			$shipping_city = Sanitize::input($request->input('shipping_info.city.value').'');
			$shipping_state = Sanitize::input($request->input('shipping_info.state.value').'');
			$shipping_postal_code = Sanitize::input($request->input('shipping_info.postal_code.value').'');
			$shipping_country_id = Sanitize::input($request->input('shipping_info.country.value').'');
			if($copy_to_shipping){
				$shipping_street = $billing_street;
				$shipping_apt = $billing_apt;
				$shipping_city = $billing_city;
				$shipping_state = $billing_state;
				$shipping_postal_code = $billing_postal_code;
				$shipping_country_id = $billing_country_id;
			}

			/* client settings */
			$currency_id = Sanitize::input($request->input('settings.currency.value'));
			$payment_terms = Sanitize::input($request->input('settings.payment_terms.value'));
			$quote_valid_days = Sanitize::input($request->input('settings.quote_valid.value'));
			$send_reminders = Sanitize::input($request->input('settings.send_reminder.value'));
			$size = Sanitize::input($request->input('settings.size.value'));
			$industry_id = Sanitize::input($request->input('settings.industry.value'));

			if($add){
				$client = new Client();
			}
			
			$client->company_id = $company_id;
			$client->first_name = $personal_info_first_name;
			$client->last_name = $personal_info_last_name;
			$client->tax_number = $personal_info_tax_id;
			$client->website = $website;
			$client->email = $email;
			$client->phone = $phone;
			
			$client->billing_street = $billing_street;
			$client->billing_apt = $billing_apt;
			$client->billing_city = $billing_city;
			$client->billing_state = $billing_state;
			$client->billing_postal_code = $billing_postal_code;
			$client->billing_country_id = $billing_country_id;

			$client->shipping_street = $shipping_street;
			$client->shipping_apt = $shipping_apt;
			$client->shipping_city = $shipping_city;
			$client->shipping_state = $shipping_state;
			$client->shipping_postal_code = $shipping_postal_code;
			$client->shipping_country_id = $shipping_country_id;

			$client->currency_id = $currency_id;
			$client->payment_terms = $payment_terms;
			$client->quote_valid_days = $quote_valid_days;
			$client->send_reminders = $send_reminders;
			$client->size = $size;
			$client->industry_id = $industry_id;
			$saved = $client->save();

			$this->upsertContactInfoForClient($request, $client->id, $add);
			$this->upsertCustomFieldValues($request, $client->id, ClientsCustomField::class, ClientCustomFieldValue::class, 'clients_flat', 'client', $add);

			if($saved){
				return response(['message' => 'Client saved successfully', 'validity' => 'client_saved'], 200);
			}else{
				return General::wentWrong();
			}
			
			

		}catch(Exception $e){
			return General::wentWrong();
		}

	}
	

}
