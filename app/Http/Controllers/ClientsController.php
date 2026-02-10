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
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\CustomFields\CustomFields;
use App\Modules\DataTable\DataTable;
use App\Services\Client\ClientService;
use App\Services\Client\Exceptions\ClientException;
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

	//use CustomFieldsPrinting, CustomFieldsValidation, CustomFieldsUpsert, ArrangedColumns;

	public function __construct(private DataTable $datatable, private CustomFields $custom_fields, private ArrangedDataTableColumns $arranged_data_table_columns, private ClientService $client_service){}

	public function fetchClientsCustomFields(Request $request){

		return $this->client_service->fetchCustomFields($request);

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

	// private function validatePersonInfo(Request $request){

	// 	$validation_rules1 = [
	// 		'personal_info.first_name.value'	=>	'required',
	// 		'personal_info.last_name.value'		=>	'required',
	// 		'personal_info.email.value'			=>	'required|email'
	// 	];

	// 	$personal_info_validation = Validator::make($request->all(), $validation_rules1);
	// 	if($personal_info_validation->fails()){
	// 		return response(['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab1', 'tab_switch' => 0], config('global.error_code'));
	// 	}

	// 	return null;
	// }

	// private function validateContactInfo(Request $request){

	// 	$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab2', 'tab_switch' => 1];

	// 	if(!$request->has('contact_info')){
	// 		return response($response, config('global.error_code'));
	// 	}

	// 	$contact_info = $request->input('contact_info');
	// 	if(empty($contact_info)){
	// 		$response['message'] = 'Please have at least one contact info added';
	// 		return response($response, config('global.error_code'));
	// 	}

	// 	$validation_rules2 = [
	// 		'contact_info'							=>	'required|array|min:1',
	// 		'contact_info.*.id'						=>	'required',
	// 		'contact_info.*.first_name.value'		=>	'required',
	// 		'contact_info.*.last_name.value'		=>	'required',
	// 		'contact_info.*.email.value'			=>	'required|email',
	// 	];

	// 	$contact_info_validation = Validator::make($request->all(), $validation_rules2);

	// 	if($contact_info_validation->fails()){
	// 		return response($response, config('global.error_code'));
	// 	}

	// 	return null;
	// }

	// private function validateBillingNShippingInfo(Request $request, bool $copy_to_shipping){

	// 	$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab3', 'tab_switch' => 2];

	// 	if(!$request->has('billing_info')){
	// 		return response($response, config('global.error_code'));
	// 	}

	// 	$validation_rules3 = [
	// 		'billing_info.street.value'						=>	'required',
	// 		'billing_info.apt.value'						=>	'required',
	// 		'billing_info.city.value'						=>	'required',
	// 		'billing_info.state.value'						=>	'required',
	// 		'billing_info.postal_code.value'				=>	'required',
	// 		'billing_info.country.value'					=>	'required|exists:countries,id',
	// 	];

	// 	$billing_info_validation = Validator::make($request->all(), $validation_rules3);
	// 	if($billing_info_validation->fails()){
	// 		return response($response, config('global.error_code'));
	// 	}

	// 	if($copy_to_shipping === false){

	// 		$validation_rules3 = [
	// 			'shipping_info.street.value'						=>	'required',
	// 			'shipping_info.apt.value'							=>	'required',
	// 			'shipping_info.city.value'							=>	'required',
	// 			'shipping_info.state.value'							=>	'required',
	// 			'shipping_info.postal_code.value'					=>	'required',
	// 			'shipping_info.country.value'						=>	'required|exists:countries,id',
	// 		];

	// 		$shipping_info_validation = Validator::make($request->all(), $validation_rules3);
	// 		if($shipping_info_validation->fails()){
	// 			return response($response, config('global.error_code'));
	// 		}

	// 	}
		
	// 	return null;

	// }

	// private function validateSettings(Request $request){

	// 	$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab5', 'tab_switch' => 4];

	// 	if(!$request->has('settings')){
	// 		return response($response, config('global.error_code'));
	// 	}

	// 	$settings_rules = [
	// 		'settings.currency.value'								=>	'required|exists:currencies,id',
	// 		'settings.industry.value'								=>	'required|exists:industries,id',
	// 		'settings.payment_terms.value'							=>	'required|in:0,7,14,30,60,90',
	// 		'settings.quote_valid.value'							=>	'required|in:0,7,14,30,60,90',
	// 		'settings.send_reminder.value'							=>	'required|in:0,1',
	// 		'settings.size.value'									=>	'required',
	// 	];

	// 	$settings_validation = Validator::make($request->all(), $settings_rules);
	// 	if($settings_validation->fails()){
	// 		return response($response, config('global.error_code'));
	// 	}

	// 	return null;

	// }

	public function index(Request $request){
		
		try{
			return $this->client_service->fetchIndex($request);
		}catch(ClientException $e){
			return response($e->getMessage(), $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'clients', 'clients', ClientsCustomField::class, 'client');
	}
	

	public function saveArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->saveArrangedColumnsData($request, ClientsCustomField::class, 'clients', 'clients', 'client');
	}

	public function destroy(Request $request){
		
		$ids = $request->input('ids');
		
		try{
			$ids = Sanitize::recursive($ids);
			$this->client_service->deleteClients($ids);
		}catch(ClientException $e){
			return response($e->getMessage(), $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function show(Request $request, int $id){
		
		try{

			$id = Sanitize::input($id);

			return $this->client_service->fetchSingleClientById($id);

		}catch(ClientException $e){
			return response($e->getMessage(), $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

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
