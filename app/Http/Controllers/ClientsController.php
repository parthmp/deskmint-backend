<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Client;
use App\Models\ClientContactInfo;
use App\Models\ClientCustomFieldValue;
use App\Models\ClientsCustomField;
use App\Models\SettingsIndexColumn;
use App\Models\UserIndexColumn;
use App\Services\DataTable;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ClientsController extends Controller{

	public function fetchClientsCustomFields(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();

		return $this->adjustRowsPrinting($fields);

	}

	private function adjustRowsPrinting($fields){ 

		$full_width_types = [
			config('global.field_types')[1],
			config('global.field_types')[9]
		];

		$date_formats = [
			'Y-m-d',        // 2025-08-19
			'd/m/Y',        // 19/08/2025
			'm/d/Y',        // 08/19/2025
			'd-M-Y',        // 01-Jan-2025
			'j-M-Y',        // 1-Jan-2025
			'j M Y',        // 1 Jan 2025
		];

		$datetime_formats = [
			// Date + 24h time
			'Y-m-d H:i',
			'Y-m-d H:i:s',
			'd/m/Y H:i',
			'd/m/Y H:i:s',
			'm/d/Y H:i',
			'm/d/Y H:i:s',
			'd-M-Y H:i',
			'd-M-Y H:i:s',
			'j-M-Y H:i',
			'j-M-Y H:i:s',
			'Y m d H:i',
			'Y m d H:i:s',
			'd m Y H:i',
			'd m Y H:i:s',
			'm d Y H:i',
			'm d Y H:i:s',
			'd M Y H:i',
			'd M Y H:i:s',
			'j M Y H:i',
			'j M Y H:i:s',

			// Date + 12h time with AM/PM
			'Y-m-d h:i A',
			'Y-m-d h:i:s A',
			'd/m/Y h:i A',
			'd/m/Y h:i:s A',
			'm/d/Y h:i A',
			'm/d/Y h:i:s A',
			'd-M-Y h:i A',
			'd-M-Y h:i:s A',
			'j-M-Y h:i A',
			'j-M-Y h:i:s A',
			'Y m d h:i A',
			'Y m d h:i:s A',
			'd m Y h:i A',
			'd m Y h:i:s A',
			'm d Y h:i A',
			'm d Y h:i:s A',
			'd M Y h:i A',
			'd M Y h:i:s A',
			'j M Y h:i A',
			'j M Y h:i:s A',
		];

		$rows = [];
		$current_row = [];

		foreach($fields as $field){

			$current_type = $field->customFieldType->input_type;
			

			if(in_array($current_type, $full_width_types)){
				
				if(!empty($current_row)){
					$rows[] = $current_row;
					$current_row = [];
				}
				
				$rows[] = [$field];

			}else{

				$current_row[] = $field;
				if(count($current_row) == 3){
					$rows[] = $current_row;
					$current_row = [];
				}
			
			}

		}

		if(!empty($current_row)){
			$rows[] = $current_row;
		}
		$index = 0;
		foreach($rows as $row){

			$count = count($row);
			$span = 12;
			
			if($count === 2){
				$span = 6;
			}
			
			if($count === 3){
				$span = 4;
			}
			
			foreach($row as $field){

				$field->span = $span;

				$field->value = trim($field->default_value);
				$field->error = '';
		
				if(isset($field->type_params) && $field->type_params !== ''){
					$temp = array_map('trim', explode(',', $field->type_params));
					$params = [];
					for($z = 0 ; $z < count($temp) ; $z++){
						$params[] = [
							'value'	=>	$temp[$z],
							'text'	=>	$temp[$z]
						];
					}
					$field->type_params = $params;
					$params = null;
				}else{
					$field->type_params = [];
				}

				$required = false;
				if($field->required === 1){
					$required = true;
				}
				
				$field->required = $required;

				if($field->customFieldType->input_type === config('global.field_types')[4]){
					if(filter_var($field->default_value, FILTER_VALIDATE_INT) === false){
						$field->value = '';
					}
				}

				if($field->customFieldType->input_type === config('global.field_types')[5]){ //date only
					
					$default_value = trim($field->default_value);
					$parsed = false;
					
					foreach($date_formats as $format){
						if((\DateTime::createFromFormat($format, $default_value) !== false)){
							$default_value = \DateTime::createFromFormat($format, $default_value)->format('Y-m-d');
							$parsed = true;
							break;
						}
					}
					
					if($parsed){
						$field->value = $default_value;
					}else{
						$field->value = '';
					}

					$field->default_value = '';
					
				}

				if($field->customFieldType->input_type === config('global.field_types')[6]){

					$default_value = trim($field->default_value);

					$field->value = '';
					if(General::isValidTime($default_value)){
						$field->value = General::convertToStandardTime($default_value);
					}
					
					$field->default_value = '';

				}

				if($field->customFieldType->input_type === config('global.field_types')[7]){
					
					$default_value = trim($field->default_value);
					$parsed = null;

					foreach ($datetime_formats as $format) {
						if((\DateTime::createFromFormat($format, $default_value) !== false)){
							$default_value = \DateTime::createFromFormat($format, $default_value)->format('Y-m-d H:i:s');
							$parsed = true;
							break;
						}
					}
					
					if($parsed){
						$field->value = $default_value;
					}else{
						$field->value = '';
					}

					$field->default_value = '';
					
				}

				if($field->customFieldType->input_type === config('global.field_types')[8]){
					
					$default_value = trim($field->default_value);

					$field->default_value = '';
					$field->value = '';
					if(General::isValidPhoneNumber($default_value)){
						$field->value = $default_value;
					}

				}


				if($field->customFieldType->input_type === config('global.field_types')[9]){
					
					$default_value = trim($field->default_value);
					$field->value = [$default_value];

					
					$field->default_value = '';
					
				}

				$field->ref = "cf_client_".$index."_".General::onlyLettersAndNumbers($field->label);
				
				$index++;
				
				
			}

		}
		
		return collect($rows)->flatten();

	}

	public function store(Request $request){
		
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

		$custom_fields_validation = $this->validateCustomFields($request);
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

			
			$client = new Client();
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
			$added = $client->save();

			$this->insertContactInfoForClient($request, $client->id);
			$this->insertClientCustomFieldValues($request, $client->id, $company_id);

			if($added){
				return response(['message' => 'Client added successfully', 'validity' => 'client_added'], 200);
			}else{
				return General::wentWrong();
			}
			
			

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	private function insertClientCustomFieldValues(Request $request, int $client_id, int $company_id){

		$db_custom_fields = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->get();

		$insert = [];
		$insert_flat = [];
		$insert_flat['client_id'] = $client_id;

		foreach($db_custom_fields as $field){

			$custom_fields_submitted = $request->input('custom_fields');

			$value = '';
			$flat_value = '';

			for($z = 0 ; $z < count($custom_fields_submitted) ; $z++){

				if($custom_fields_submitted[$z]['id'] == $field->id){

					if($field->customFieldType->input_type === config('global.field_types')[0] || $field->customFieldType->input_type === config('global.field_types')[1] || $field->customFieldType->input_type === config('global.field_types')[3] || $field->customFieldType->input_type === config('global.field_types')[2] || $field->customFieldType->input_type === config('global.field_types')[4] || $field->customFieldType->input_type === config('global.field_types')[8]){ //text, textarea, select, email, number, telephone

						$value = trim($custom_fields_submitted[$z]['value']);
						$flat_value = $value;
						if($field->customFieldType->input_type === config('global.field_types')[4] && trim($custom_fields_submitted[$z]['value']) == ''){
							$flat_value = null;
						}

					}else{

						if($field->customFieldType->input_type === config('global.field_types')[5] || $field->customFieldType->input_type === config('global.field_types')[7]){ //date and datetime

							if($custom_fields_submitted[$z]['value'] === null || $custom_fields_submitted[$z]['value'] === ''){

								$value = '';
								$flat_value = null;

							}else{
								
								
								$datetime_string = trim($custom_fields_submitted[$z]['value']);

								if(General::isValidISODateTime($datetime_string)){
									
									$value = $datetime_string;
									$flat_value = Carbon::parse($datetime_string)->format('Y-m-d H:i:s'); //for date and time
									
								}else{
									$value = '';
									$flat_value = null;
								}

							}
							

						}else if($field->customFieldType->input_type === config('global.field_types')[6]){ //time

							if($field->required == 1){
								$value = General::jsonTimeToAmPm(json_encode($custom_fields_submitted[$z]['value']));
								$flat_value = $value;
							}else{
								if($custom_fields_submitted[$z]['value'] !== null){
									$value = General::jsonTimeToAmPm(json_encode($custom_fields_submitted[$z]['value']));
									$flat_value = $value;
								}else{
									$value = json_encode('');
									$flat_value = '';
								}
							}

						}else if($field->customFieldType->input_type === config('global.field_types')[9]){ //multiselect
							$value = json_encode('');
							if($custom_fields_submitted[$z]['value'] !== null){
								$value = json_encode($custom_fields_submitted[$z]['value']);
								$flat_value = $value;
							}
						}

					}

					if($custom_fields_submitted[$z]['id'] == $field->id){
						$custom_column_name = General::replaceWithUnderscores($field->label);
						$insert_flat[$custom_column_name] = $flat_value;
					}

				}
			}

			$insert[] = [
				'client_id'					=>		$client_id,
				'clients_custom_field_id'	=>		$field->id,
				'field_value'				=>		$value,
				'created_at'				=>		now(),
				'updated_at'				=>		now()
			];

		}

		ClientCustomFieldValue::insert($insert);
		
		$insert_flat['created_at'] = now();
		$insert_flat['updated_at'] = now();
		DB::table('clients_flat')->insert($insert_flat);
		
	}

	private function insertContactInfoForClient(Request $request, int $client_id){

		$contact_info = $request->input('contact_info');

		$insert = [];

		foreach($contact_info as $info){

			$first_name = Sanitize::input($info['first_name']['value']);
			$last_name = Sanitize::input($info['last_name']['value']);
			$email = Sanitize::input($info['email']['value']);
			$phone = Sanitize::input($info['phone']['value'].'');

			$insert[] = [
				'client_id'		=>	$client_id,
				'first_name'	=>	$first_name,
				'last_name'		=>	$last_name,
				'email'			=>	$email,
				'phone'			=>	$phone,
				'deleted_at'	=>	null,
				'created_at'	=>	now(),
   				'updated_at'	=>	now(),
			];

		}

		ClientContactInfo::insert($insert);

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

	private function validateCustomFields(Request $request){

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab4', 'tab_switch' => 3];

		if(!$request->has('billing_info')){
			return response($response, config('global.error_code'));
		}

		$company_id = Sanitize::input($request->input('company_id'));

		$db_custom_fields = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->get();

		if(empty($db_custom_fields)){
			return null;
		}

		$validation_rules = [
			'custom_fields'	 =>	'required|array|min:1'
		];

		$custom_fields_validation_1 = Validator::make($request->all(), $validation_rules);
		if($custom_fields_validation_1->fails()){
			return response($response, config('global.error_code'));
		}

		$custom_fields_submitted = $request->input('custom_fields');

		$validation_rules = [];

		/* generate validation rules dynamically */
		foreach($db_custom_fields as $field){

			if($field->required == 1){

				for($z = 0 ; $z < count($custom_fields_submitted) ; $z++){

					if($custom_fields_submitted[$z]['id'] == $field->id){

						if($field->customFieldType->input_type === config('global.field_types')[0] || $field->customFieldType->input_type === config('global.field_types')[1] || $field->customFieldType->input_type === config('global.field_types')[3] || $field->customFieldType->input_type === config('global.field_types')[9]){

							$validation_rules['custom_fields.'.$z.'.value'] = 'required';

						}else{

							if($field->customFieldType->input_type === config('global.field_types')[2]){ //email
								
								$validation_rules['custom_fields.'.$z.'.value'] = 'required|email';

							}else if($field->customFieldType->input_type === config('global.field_types')[4]){ //number

								$validation_rules['custom_fields.'.$z.'.value'] = 'required|numeric';

							}else if($field->customFieldType->input_type === config('global.field_types')[5] || $field->customFieldType->input_type === config('global.field_types')[7]){ //date and datetime

								$validation_rules['custom_fields.'.$z.'.value'] = 'required|date';

							}else if($field->customFieldType->input_type === config('global.field_types')[6]){ //time

								$validation_rules['custom_fields.'.$z.'.value'] = 'required|array';
								$validation_rules['custom_fields.'.$z.'.value.hours'] = 'required|integer|between:0,23';
								$validation_rules['custom_fields.'.$z.'.value.minutes'] = 'required|integer|between:0,59';
								$validation_rules['custom_fields.'.$z.'.value.seconds'] = 'required|integer|between:0,59';

							}else if($field->customFieldType->input_type === config('global.field_types')[8]){ //telephone

								$validation_rules['custom_fields.'.$z.'.value'] = 'required|regex:/^\+?\d+$/';

							}

						}
						

					}

				}

			}

		}

		$custom_fields_validation_2 = Validator::make($request->all(), $validation_rules);
		if($custom_fields_validation_2->fails()){
			return response($response, config('global.error_code'));
		}

		return null;

	}

	private function validateSettings(Request $request){

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab4', 'tab_switch' => 4];

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
		// $custom_fields_ids = [];
		// $custom_columns = UserIndexColumn::where([['company_id', '=', $company_id], ['user_id', '=', Auth::user()->id], ['feature_name', '=', 'clients']])->first();
		// if(!$custom_columns){
		// 	$custom_columns = SettingsIndexColumn::where([['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();
		// }

		// $json_decoded = [];

		// if($custom_columns){
			
		// 	$custom_columns = json_decode($custom_columns->columns_json);
		// 	$json_decoded = $custom_columns;
		// 	$custom_columns = $custom_columns->custom_fields;
			
		// 	foreach($custom_columns as $c_column){
		// 		$custom_fields_ids[] = $c_column->clients_custom_fields_id;
		// 	}

		// }

		$fields = DataTable::sortNPaginate(
			$request,
			\App\Models\Client::class,
			['deleted_at', 'updated_at'],
			$company_id,
			['clients.created_at'],
			[
				[
					'table' => 'clients_flat',
					'first' => 'clients.id',
					'operator' => '=',
					'second' => 'clients_flat.client_id',
					'columns' => ['clients_flat.date_required as date_required', 'clients_flat.multiselect_not_required as multiselect_not_required']
				]
			]
		);

		
		/*hide time for custom date fields*/
		// $fields->each(function($ele){

		// 	$ele->input_type = ucfirst($ele->input_type);

		// 	if((int)$ele->required === 0){
		// 		$ele->required = [
		// 			'type'		=>	'label',
		// 			'highlight'	=>	'error',
		// 			'text'		=>	'No'
		// 		];
		// 	}else{
		// 		$ele->required = [
		// 			'type'		=>	'label',
		// 			'highlight'	=>	'success',
		// 			'text'		=>	'Yes'
		// 		];
		// 	}

		// });
		
		$table_data = [
			'columns' => [
				[
					'label' => 	'date_required',
					'text'	=>	'date r'
				],
				[
					'label' => 	'first_name',
					'text'	=>	'First name'
				],
				[
					'label' => 	'multiselect_not_required',
					'text'	=>	'm r'
				],
				[
					'label' => 	'last_name',
					'text'	=>	'Last name'
				],
				[
					'label' => 	'email',
					'text'	=>	'Email'
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
		
		//$table_data['columns'] = DataTable::modifyForColumns($table_data['columns'], $json_decoded);
		
		$total_pages = $fields->lastPage();

		return [
			'table_data'	=>		$table_data,
			'total_pages'	=>		$total_pages,
			'current_page'	=>		$fields->currentPage()
		];

	}

	public function fetchArrangedColumns(Request $request){
		
		$company_id = Sanitize::input($request->input('company_id'));

		/* check if user has any data */
		$user_data = UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();

		if(!$user_data){
			$user_data = SettingsIndexColumn::where([['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();
		}

		/* fetch all fields */
		$clients_columns = Schema::getColumnListing('clients');
		$clients_columns = array_values(array_diff($clients_columns, ['deleted_at', 'updated_at']));
		
		$clients_custom_columns = ClientsCustomField::where('company_id', '=', $company_id)->get()->toArray();

		$merged = [];

		/* handle userdata here */
		$user_custom_fields = [];
		$user_fields = [];
		if($user_data){
			
			$fields_json = json_decode($user_data->columns_json, true);

			$saved_labels_normal = [];
			$saved_labels_custom = [];

			for($z = 0 ; $z < count($fields_json) ; $z++){
				if($fields_json[$z]['type'] === 'normal'){
					$saved_labels_normal[] = $fields_json[$z]['label'];
				}else{
					$saved_labels_custom[] = $fields_json[$z]['label'];
				}
			}

			$clients_columns = Schema::getColumnListing('clients');
			$clients_columns = array_values(array_diff($clients_columns, ['deleted_at', 'updated_at']));
			
			$clients_custom_columns = ClientsCustomField::where('company_id', '=', $company_id)->pluck('label')->map(function($label){
				return General::replaceWithUnderscores($label);
			})->toArray();

			foreach($fields_json as $field){

				if(in_array($field['label'], $clients_columns) || in_array(General::replaceWithUnderscores($field['label']), $clients_custom_columns)){
					$user_fields[] = $field;
				}

			}

			$counter = 1;
			foreach($clients_columns as $temp_client_column){
				$to_push = [];
				if(!in_array($temp_client_column, $saved_labels_normal)){
					$to_push['id'] = $counter++;
					$to_push['label'] = $temp_client_column;
					$to_push['text'] = General::NormalizeColumnName($temp_client_column);
					if($temp_client_column === 'created_at'){
						$to_push['text'] = 'Added on';
					}
					$to_push['order'] = 0;
					$to_push['type'] = 'normal';
					$to_push['searchable'] = false;
					$to_push['show'] = false;
					$user_fields[] = $to_push;
				}
			}

			foreach($clients_custom_columns as $temp_client_custom_column){
				$to_push = [];
				if(!in_array($temp_client_custom_column, $saved_labels_custom)){
					$to_push['id'] = $counter++;
					$to_push['label'] = $temp_client_custom_column;
					$to_push['text'] = General::NormalizeColumnName($temp_client_custom_column);
					$to_push['order'] = 0;
					$to_push['type'] = 'custom';
					$to_push['searchable'] = false;
					$to_push['show'] = false;
					$user_fields[] = $to_push;
				}
			}

			return $user_fields;
			
		}else{

			
			$counter = 1;

			for($z = 0 ; $z < count($clients_columns) ; $z++){

				$to_push = [];

				$to_push['id'] = $counter++;
				$to_push['label'] = $clients_columns[$z];
				$to_push['text'] = General::NormalizeColumnName($clients_columns[$z]);
				$to_push['order'] = 0;
				$to_push['type'] = 'normal';
				$to_push['searchable'] = false;
				$to_push['show'] = false;

				if($clients_columns[$z] === 'created_at'){
					$to_push['text'] = 'Added on';
				}

				if($clients_columns[$z] === 'first_name' || $clients_columns[$z] === 'last_name' || $clients_columns[$z] === 'email' || $clients_columns[$z] === 'created_at'){
					$to_push['searchable'] = true;
					$to_push['show'] = true;
				}
				
				$merged[] = $to_push;

			}


			for($z = 0 ; $z < count($clients_custom_columns) ; $z++){

				$to_push = [];
				$to_push['id'] = $counter++;
				$to_push['label'] = General::replaceWithUnderscores($clients_custom_columns[$z]['label']);
				$to_push['text'] = ucfirst(strtolower($clients_custom_columns[$z]['label']));
				$to_push['order'] = 0;
				$to_push['type'] = 'custom';
				$to_push['clients_custom_fields_id'] = 0;
				$to_push['searchable'] = false;
				$to_push['show'] = false;

				$merged[] = $to_push;

			}

			return $merged;

		}
		


	}

	public function saveArrangedColumns(Request $request){

		$validation_rules = [
			'columns'	 =>	'required|array'
		];

		$v = Validator::make($request->all(), $validation_rules);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$columns = $request->input('columns');
		$company_id = Sanitize::input($request->input('company_id'));

		if(empty($columns)){
			UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->delete();
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}

		$clients_columns = Schema::getColumnListing('clients');
		$clients_columns = array_values(array_diff($clients_columns, ['deleted_at', 'updated_at']));
		
		$clients_custom_columns = ClientsCustomField::where('company_id', '=', $company_id)->pluck('label')->map(function($label){
			return General::replaceWithUnderscores($label);
		})->toArray();

		$order = 1;

		for($z = 0 ; $z < count($columns) ; $z++){

			if(!in_array($columns[$z]['label'], $clients_columns) && !in_array(General::replaceWithUnderscores($columns[$z]['label']), $clients_custom_columns)){
				return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}

			$columns[$z]['order'] = $order;
			$order++;

		}
		

		$columns = json_encode($columns);
		
		UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->delete();
		
		$user_index_col = new UserIndexColumn();
		$user_index_col->user_id = Auth::user()->id;
		$user_index_col->company_id = $company_id;
		$user_index_col->feature_name = 'clients';
		$user_index_col->columns_json = $columns;
		$user_index_col->save();
		return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);




	}
	

}
