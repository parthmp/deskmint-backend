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
			'd-M-Y',        // 01-Jan-2025  
			'j-M-Y',        // 1-Jan-2025
			'd M Y',        // 21 Jan 2025 (with leading zeros)
			'j M Y',        // 1 Jan 2025 (without leading zeros)
		];

		$datetime_formats = [
			'Y-m-d h:i:s A',    // 2025-01-20 05:04:25 PM
			'd-M-Y h:i:s A',    // 20-Jan-2025 05:04:25 PM  
			'd M Y h:i:s A',    // 20 Jan 2025 05:04:25 PM
			'Y-m-d h:i A',      // 2025-01-20 05:04 PM
			'Y-m-d H:i:s',      // 2025-01-20 17:04:25
			'd-M-Y H:i:s',      // 20-Jan-2025 17:04:25
			'd M Y H:i:s',      // 20 Jan 2025 17:04:25
			'd-M-Y h:i A',      // 20-Jan-2025 05:04 PM
			'd M Y h:i A',      // 20 Jan 2025 05:04 PM
			'Y-m-d H:i',        // 2025-01-20 17:04
			'd-M-Y H:i',        // 20-Jan-2025 17:04
			'd M Y H:i'       // 20 Jan 2025 17:04
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
				$temp_params = [];
				$field->span = $span;

				$field->value = trim($field->default_value);
				$field->error = '';
				
				if(isset($field->type_params) && $field->type_params !== ''){
					$temp = array_map('trim', explode(',', $field->type_params));
					$temp_params = $temp;
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

				if($field->customFieldType->input_type === config('global.field_types')[2]){ /* email */

					if(!filter_var($field->default_value, FILTER_VALIDATE_EMAIL)){
						$field->value = '';
					}

				}

				if($field->customFieldType->input_type === config('global.field_types')[3]){ /* select */
					
					if(!in_array($field->default_value, $temp_params)){
						$field->value = '';
					}
					
				}

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

				if($field->customFieldType->input_type === config('global.field_types')[7]){ /* datetime */
					
					$default_value = General::fixMonthNames(trim($field->default_value));
					$parsed = null;

					foreach($datetime_formats as $format){

						$date_obj = \DateTime::createFromFormat($format, $default_value);
						
						if($date_obj !== false && $date_obj->format($format) === $default_value){
							
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

					if(!in_array($field->default_value, $temp_params)){
						$field->value = '';
					}else{
						$default_value = trim($field->default_value);
						$field->value = [$default_value];
					}
					$field->default_value = '';
					
				}

				$field->ref = "cf_client_".$index."_".General::onlyLettersAndNumbers($field->label);
				
				$index++;
				
				
			}

		}
		
		return collect($rows)->flatten();

	}

	public function store(Request $request){

		return $this->saveOrUpdateClient($request, true);
		
	}

	private function upsertClientCustomFieldValues(Request $request, int $client_id, int $company_id, $add = true){

		
		if($add){
			$db_custom_fields = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->get();
		}else{
			$db_custom_fields = ClientsCustomField::where('company_id', $company_id)->whereHas('customFieldType')->whereHas('customFieldValue', function($query) use ($client_id){
				$query->where('client_id', $client_id);
			})->with(['customFieldValue' => function($query) use ($client_id) {
    			$query->where('client_id', $client_id);
			}])->get();
		}
		
		$upsert = [];
		$insert_flat = [];
		$insert_flat['client_id'] = $client_id;

		foreach($db_custom_fields as $field){

			$custom_fields_submitted = $request->input('custom_fields');

			$value = '';
			$flat_value = '';

			for($z = 0 ; $z < count($custom_fields_submitted) ; $z++){

				if($custom_fields_submitted[$z]['id'] == $field->id){

					if(!$add){
						$field->value_id = $field->customFieldValue->id;
					}

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
			$temp_upsert = [];
			if(!$add){
				$temp_upsert['id'] = $field->value_id;
			}
			
			$temp_upsert['client_id'] = $client_id;
			$temp_upsert['clients_custom_field_id'] = $field->id;
			$temp_upsert['field_value'] = $value;

			$upsert[] = $temp_upsert;
		}

		if(!empty($upsert)){
			ClientCustomFieldValue::upsert($upsert, ['id'], ['client_id', 'clients_custom_field_id', 'field_value']);
		}

		$insert_flat['created_at'] = now();
		$insert_flat['updated_at'] = now();
		if(!$add){
			DB::table('clients_flat')->where('client_id', '=', $client_id)->delete();
		}
		DB::table('clients_flat')->insert($insert_flat);
		
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

	private function validateCustomFields(Request $request){

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab4', 'tab_switch' => 3];

		if(!$request->has('custom_fields')){
			return response(['message' => 'no custom fields', 'validity' => 'invalid_data_tab4', 'tab_switch' => 3], config('global.error_code'));
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

		$required_count = 0;


		$found_and_valid = 0;
		/* generate validation rules dynamically */
		foreach($db_custom_fields as $field){

			if($field->required == 1){
				$required_count++;
				for($z = 0 ; $z < count($custom_fields_submitted) ; $z++){

					if($custom_fields_submitted[$z]['id'] === $field->id){
						$found_and_valid++;
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

				if(count($custom_fields_submitted) !== count($db_custom_fields) && $found_and_valid === $required_count){
					return response($response, config('global.error_code'));
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
		
		$fields = DataTable::sortNPaginate(
			$request,
			\App\Models\Client::class,
			['deleted_at', 'updated_at'],
			$company_id,
			$searchable_dates,
			[
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
			],
			[],
			$searchable_columns
		);
		
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
		
		$company_id = Sanitize::input($request->input('company_id'));

		/* check if user has any data */
		$user_data = UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();

		if(!$user_data){
			$user_data = SettingsIndexColumn::where([['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->first();
		}

		/* fetch all fields */
		$clients_columns = Schema::getColumnListing('clients');
		$clients_columns = array_values(array_diff($clients_columns, ['deleted_at', 'updated_at']));
		
		$clients_custom_columns = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->with('customFieldType')->get()->toArray();

		$user_fields = [];

		/* handle userdata here */
		if($user_data){
			
			$fields_json = json_decode($user_data->columns_json, true);

			$saved_labels_normal = [];
			$saved_ids_custom = [];
			
			$splitted = $this->splitFields($fields_json);
			$saved_labels_normal = $splitted['normal'];
			$saved_ids_custom = $splitted['custom'];

			$clients_columns = Schema::getColumnListing('clients');
			$clients_columns = array_values(array_diff($clients_columns, ['deleted_at', 'updated_at']));
			
			$clients_custom_columns_ids = ClientsCustomField::where('company_id', '=', $company_id)->pluck('id')->toArray();
			
			foreach($fields_json as $field){

				if(in_array($field['label'], $clients_columns) || in_array($field['clients_custom_fields_id'], $clients_custom_columns_ids)){
					$user_fields[] = $field;
				}

			}

			$counter = 1;
			foreach($clients_columns as $temp_client_column){
				$to_push = [];
				if(!in_array($temp_client_column, $saved_labels_normal)){
					$to_push['id'] = $counter++;
					$to_push['label'] = $temp_client_column;
					$to_push['is_date'] = false;
					$to_push['text'] = General::NormalizeColumnName($temp_client_column);
					if($temp_client_column === 'created_at'){
						$to_push['text'] = 'Added on';
						$to_push['is_date'] = true;
					}
					$to_push['type'] = 'normal';
					$to_push['searchable'] = false;
					$to_push['show'] = false;
					
					$user_fields[] = $to_push;
				}
			}

			/* modify text to show */
			for($z = 0 ; $z < count($user_fields) ; $z++){

				if($user_fields[$z]['type'] === 'custom'){

					for($x = 0 ; $x < count($clients_custom_columns) ; $x++){

						if($clients_custom_columns[$x]['id'] === $user_fields[$z]['clients_custom_fields_id']){

							$user_fields[$z]['text'] = General::NormalizeColumnName($clients_custom_columns[$x]['label']);
							$user_fields[$z]['is_date'] = false;
							if($clients_custom_columns[$x]['custom_field_type']['input_type'] === config('global.field_types')[5] || $clients_custom_columns[$x]['custom_field_type']['input_type'] === config('global.field_types')[7]){
								$user_fields[$z]['is_date'] = true;
							}

						}

					}

				}

			}

			/*  */

			foreach($clients_custom_columns as $t_clients_custom_columns){
				$to_push = [];
				if($t_clients_custom_columns['custom_field_type']['input_type'] !== config('global.field_types')[9]){
					if(!in_array($t_clients_custom_columns['id'], $saved_ids_custom)){
						$to_push['id'] = $counter++;
						$to_push['label'] = '-';
						$to_push['text'] = General::NormalizeColumnName($t_clients_custom_columns['label']);
						$to_push['type'] = 'custom';
						$to_push['clients_custom_fields_id'] = $t_clients_custom_columns['id'];
						$to_push['searchable'] = false;
						$to_push['show'] = false;
						$to_push['is_date'] = false;
						if($t_clients_custom_columns['custom_field_type']['input_type'] === config('global.field_types')[5] || $t_clients_custom_columns['custom_field_type']['input_type'] === config('global.field_types')[7]){
							$to_push['is_date'] = true;
						}
						$user_fields[] = $to_push;
					}
				}
			}

			return $user_fields;
			
		}else{
			return $this->nonUserDataColumns($clients_columns, $clients_custom_columns);
		}
		


	}

	private function splitFields(array $json_fields) : array{

		$saved_labels_normal = [];
		$saved_ids_custom = [];

		for($z = 0 ; $z < count($json_fields) ; $z++){
			if($json_fields[$z]['type'] === 'normal'){
				$saved_labels_normal[] = $json_fields[$z]['label'];
			}else{
				$saved_ids_custom[] = $json_fields[$z]['clients_custom_fields_id'];
			}
		}

		return ['normal' => $saved_labels_normal, 'custom' => $saved_ids_custom];

	}

	private function nonUserDataColumns(array $columns, array $custom_columns) : array{

		$counter = 1;

		$merged = [];

		for($z = 0 ; $z < count($columns) ; $z++){

			$to_push = [];
			$to_push['id'] = $counter++;
			$to_push['label'] = $columns[$z];
			$to_push['text'] = General::NormalizeColumnName($columns[$z]);
			$to_push['type'] = 'normal';
			$to_push['is_date'] = false;
			$to_push['searchable'] = false;
			$to_push['show'] = false;

			if($columns[$z] === 'created_at'){
				$to_push['text'] = 'Added on';
				$to_push['is_date'] = true;
			}

			if($columns[$z] === 'first_name' || $columns[$z] === 'last_name' || $columns[$z] === 'email' || $columns[$z] === 'created_at'){
				$to_push['searchable'] = true;
				$to_push['show'] = true;
			}
			
			$merged[] = $to_push;

		}
		
		for($z = 0 ; $z < count($custom_columns) ; $z++){

			$to_push = [];
			if($custom_columns[$z]['custom_field_type']['input_type'] !== config('global.field_types')[9]){
				
				$to_push['id'] = $counter++;
				$to_push['label'] = '-';
				$to_push['text'] = ucfirst(strtolower($custom_columns[$z]['label']));
				$to_push['type'] = 'custom';
				$to_push['clients_custom_fields_id'] = $custom_columns[$z]['id'];
				$to_push['searchable'] = false;
				$to_push['show'] = false;
				$to_push['is_date'] = false;
				
				if($custom_columns[$z]['custom_field_type']['input_type'] === config('global.field_types')[5] || $custom_columns[$z]['custom_field_type']['input_type'] === config('global.field_types')[7]){
					$to_push['is_date'] = true;
				}

				$merged[] = $to_push;
			}

		}

		return $merged;

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
		
		$clients_custom_column_ids = ClientsCustomField::where('company_id', '=', $company_id)->pluck('id')->toArray();

		for($z = 0 ; $z < count($columns) ; $z++){

			if(!in_array($columns[$z]['label'], $clients_columns) && !in_array($columns[$z]['clients_custom_fields_id'], $clients_custom_column_ids)){
				return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}

		}
		
		try{

			$columns = json_encode($columns);
			
			UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', 'clients']])->delete();
			
			$user_index_col = new UserIndexColumn();
			$user_index_col->user_id = Auth::user()->id;
			$user_index_col->company_id = $company_id;
			$user_index_col->feature_name = 'clients';
			$user_index_col->columns_json = $columns;
			$user_index_col->save();
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function destroy(Request $request){

		$ids = $request->input('ids');

		if (!is_array($ids) || empty($ids)) {
			return response(['message' => 'No valid IDs provided', 'validity' => 'invalid_ids'], config('global.error_code'));
		}

		foreach ($ids as $id){
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
			$this->upsertClientCustomFieldValues($request, $client->id, $company_id, $add);

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
