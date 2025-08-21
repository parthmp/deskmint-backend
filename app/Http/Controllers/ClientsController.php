<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
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

				$field->value = $field->default_value;
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

				if($field->customFieldType->input_type === config('global.field_types')[5]){
					
					$default_value = trim($field->default_value);
					$parsed = null;

					foreach ($date_formats as $format) {
						if((\DateTime::createFromFormat($format, $default_value) !== false)){
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
						$field->value = $default_value;
					}
					
					$field->default_value = '';

				}

				if($field->customFieldType->input_type === config('global.field_types')[7]){
					
					$default_value = trim($field->default_value);
					$parsed = null;

					foreach ($datetime_formats as $format) {
						if((\DateTime::createFromFormat($format, $default_value) !== false)){
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

		/*
		{
			"personal_info": {
				"first_name": {
					"value": "dsf",
					"error": null
				},
				"last_name": {
					"value": "sdfsdfsdf",
					"error": null
				},
				"tax_id": {
					"value": "awd",
					"error": "Last name is required"
				},
				"website": {
					"value": "dawdwad",
					"error": "Last name is required"
				},
				"email": {
					"value": "awd@dfsf.com",
					"error": null
				},
				"phone": {
					"value": "345345",
					"error": "Phone number is required"
				}
			},
			"contact_info": [
				{
					"id": 1755806462946,
					"first_name": {
						"value": "sdf",
						"error": null
					},
					"last_name": {
						"value": "awd",
						"error": null
					},
					"email": {
						"value": "dw@asfsdf.com",
						"error": null
					},
					"phone": {
						"value": "113212",
						"error": null
					}
				}
			],
			"billing_info": {
				"street": {
					"value": "q",
					"error": null
				},
				"apt": {
					"value": "w",
					"error": null
				},
				"city": {
					"value": "e",
					"error": null
				},
				"state": {
					"value": "a",
					"error": null
				},
				"postal_code": {
					"value": "dw",
					"error": null
				},
				"country": {
					"value": "99",
					"error": null
				}
			},
			"shipping_info": {
				"street": {
					"value": "d",
					"error": null
				},
				"apt": {
					"value": "f",
					"error": null
				},
				"city": {
					"value": "g",
					"error": null
				},
				"state": {
					"value": "c",
					"error": null
				},
				"postal_code": {
					"value": "sf",
					"error": null
				},
				"country": {
					"value": "226",
					"error": null
				}
			},
			"custom_fields": [
				{
					"id": 539,
					"custom_field_type_id": 612,
					"company_id": 4,
					"label": "client date - required",
					"placeholder": "client date - required",
					"required": true,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 0,
					"order_column_on_index_page": 0,
					"show_on_index_page": 0,
					"deleted_at": null,
					"created_at": "2025-08-20T21:53:36.000000Z",
					"updated_at": "2025-08-20T21:53:36.000000Z",
					"span": 4,
					"value": "2025-08-22T20:01:00.000Z",
					"error": null,
					"ref": "cf_client_0_clientdaterequired",
					"custom_field_type": {
						"id": 612,
						"input_type": "date",
						"input_name": "Client date",
						"deleted_at": null,
						"created_at": "2025-08-20T21:45:07.000000Z",
						"updated_at": "2025-08-20T21:45:39.000000Z"
					}
				},
				{
					"id": 540,
					"custom_field_type_id": 612,
					"company_id": 4,
					"label": "Client date - not required",
					"placeholder": "Client date - not required",
					"required": false,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 0,
					"order_column_on_index_page": 0,
					"show_on_index_page": 0,
					"deleted_at": null,
					"created_at": "2025-08-20T22:10:36.000000Z",
					"updated_at": "2025-08-20T22:11:02.000000Z",
					"span": 4,
					"value": "2022-01-05T00:00:00.000Z",
					"error": null,
					"ref": "cf_client_1_clientdatenotrequired",
					"custom_field_type": {
						"id": 612,
						"input_type": "date",
						"input_name": "Client date",
						"deleted_at": null,
						"created_at": "2025-08-20T21:45:07.000000Z",
						"updated_at": "2025-08-20T21:45:39.000000Z"
					}
				},
				{
					"id": 542,
					"custom_field_type_id": 613,
					"company_id": 4,
					"label": "Client datetime - not required",
					"placeholder": "Client datetime - not required",
					"required": false,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 0,
					"order_column_on_index_page": 0,
					"show_on_index_page": 0,
					"deleted_at": null,
					"created_at": "2025-08-20T22:15:33.000000Z",
					"updated_at": "2025-08-20T22:15:33.000000Z",
					"span": 4,
					"value": "2021-05-06T08:35:16.000Z",
					"error": null,
					"ref": "cf_client_2_clientdatetimenotrequired",
					"custom_field_type": {
						"id": 613,
						"input_type": "datetime",
						"input_name": "Client datetime",
						"deleted_at": null,
						"created_at": "2025-08-20T21:45:54.000000Z",
						"updated_at": "2025-08-20T21:45:54.000000Z"
					}
				},
				{
					"id": 543,
					"custom_field_type_id": 614,
					"company_id": 4,
					"label": "Client email - required",
					"placeholder": "Client email - required",
					"required": true,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 1,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:16:24.000000Z",
					"updated_at": "2025-08-20T22:21:36.000000Z",
					"span": 4,
					"value": "dsf@dfsdf.com",
					"error": null,
					"ref": "cf_client_3_clientemailrequired",
					"custom_field_type": {
						"id": 614,
						"input_type": "email",
						"input_name": "Client email",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:00.000000Z",
						"updated_at": "2025-08-20T21:46:00.000000Z"
					}
				},
				{
					"id": 547,
					"custom_field_type_id": 616,
					"company_id": 4,
					"label": "Client number - required",
					"placeholder": "Client number - required",
					"required": true,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 2,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:25:40.000000Z",
					"updated_at": "2025-08-20T22:25:40.000000Z",
					"span": 4,
					"value": "123456",
					"error": null,
					"ref": "cf_client_4_clientnumberrequired",
					"custom_field_type": {
						"id": 616,
						"input_type": "number",
						"input_name": "Client number",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:14.000000Z",
						"updated_at": "2025-08-20T21:46:14.000000Z"
					}
				},
				{
					"id": 541,
					"custom_field_type_id": 613,
					"company_id": 4,
					"label": "Client datetime - required",
					"placeholder": "Client datetime - required",
					"required": true,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 5,
					"order_column_on_index_page": 0,
					"show_on_index_page": 0,
					"deleted_at": null,
					"created_at": "2025-08-20T22:12:30.000000Z",
					"updated_at": "2025-08-20T22:13:15.000000Z",
					"span": 4,
					"value": "2025-08-27T20:01:00.000Z",
					"error": null,
					"ref": "cf_client_5_clientdatetimerequired",
					"custom_field_type": {
						"id": 613,
						"input_type": "datetime",
						"input_name": "Client datetime",
						"deleted_at": null,
						"created_at": "2025-08-20T21:45:54.000000Z",
						"updated_at": "2025-08-20T21:45:54.000000Z"
					}
				},
				{
					"id": 544,
					"custom_field_type_id": 614,
					"company_id": 4,
					"label": "Client email - not required",
					"placeholder": "Client email - not required",
					"required": false,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 10,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:23:28.000000Z",
					"updated_at": "2025-08-20T22:23:28.000000Z",
					"span": 12,
					"value": "saddfs@sdffd.com",
					"error": null,
					"ref": "cf_client_6_clientemailnotrequired",
					"custom_field_type": {
						"id": 614,
						"input_type": "email",
						"input_name": "Client email",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:00.000000Z",
						"updated_at": "2025-08-20T21:46:00.000000Z"
					}
				},
				{
					"id": 545,
					"custom_field_type_id": 615,
					"company_id": 4,
					"label": "Client multiselect - required",
					"placeholder": "Client multiselect - required",
					"required": true,
					"type_params": [
						{
							"value": "one",
							"text": "one"
						},
						{
							"value": "two",
							"text": "two"
						},
						{
							"value": "three",
							"text": "three"
						}
					],
					"default_value": null,
					"order_on_add_edit_page": 11,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:24:24.000000Z",
					"updated_at": "2025-08-20T22:24:42.000000Z",
					"span": 12,
					"value": [
						"three"
					],
					"error": null,
					"ref": "cf_client_7_clientmultiselectrequired",
					"custom_field_type": {
						"id": 615,
						"input_type": "multiselect",
						"input_name": "Client multiselect",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:07.000000Z",
						"updated_at": "2025-08-20T21:46:07.000000Z"
					}
				},
				{
					"id": 546,
					"custom_field_type_id": 615,
					"company_id": 4,
					"label": "Client multiselect - not required",
					"placeholder": "Client multiselect - not required",
					"required": false,
					"type_params": [
						{
							"value": "three",
							"text": "three"
						},
						{
							"value": "four",
							"text": "four"
						},
						{
							"value": "five",
							"text": "five"
						}
					],
					"default_value": null,
					"order_on_add_edit_page": 20,
					"order_column_on_index_page": 0,
					"show_on_index_page": 0,
					"deleted_at": null,
					"created_at": "2025-08-20T22:25:07.000000Z",
					"updated_at": "2025-08-20T22:25:07.000000Z",
					"span": 12,
					"value": [
						"four"
					],
					"error": null,
					"ref": "cf_client_8_clientmultiselectnotrequired",
					"custom_field_type": {
						"id": 615,
						"input_type": "multiselect",
						"input_name": "Client multiselect",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:07.000000Z",
						"updated_at": "2025-08-20T21:46:07.000000Z"
					}
				},
				{
					"id": 548,
					"custom_field_type_id": 616,
					"company_id": 4,
					"label": "Client number - not required",
					"placeholder": "Client number - not required",
					"required": false,
					"type_params": [],
					"default_value": "101520",
					"order_on_add_edit_page": 30,
					"order_column_on_index_page": 0,
					"show_on_index_page": 0,
					"deleted_at": null,
					"created_at": "2025-08-20T22:26:29.000000Z",
					"updated_at": "2025-08-20T22:26:29.000000Z",
					"span": 4,
					"value": "101520",
					"error": null,
					"ref": "cf_client_9_clientnumbernotrequired",
					"custom_field_type": {
						"id": 616,
						"input_type": "number",
						"input_name": "Client number",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:14.000000Z",
						"updated_at": "2025-08-20T21:46:14.000000Z"
					}
				},
				{
					"id": 549,
					"custom_field_type_id": 617,
					"company_id": 4,
					"label": "Client select - required",
					"placeholder": "Client select - required",
					"required": true,
					"type_params": [
						{
							"value": "one",
							"text": "one"
						},
						{
							"value": "two",
							"text": "two"
						},
						{
							"value": "three",
							"text": "three"
						}
					],
					"default_value": null,
					"order_on_add_edit_page": 30,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:29:20.000000Z",
					"updated_at": "2025-08-20T22:30:43.000000Z",
					"span": 4,
					"value": "two",
					"error": null,
					"ref": "cf_client_10_clientselectrequired",
					"custom_field_type": {
						"id": 617,
						"input_type": "select",
						"input_name": "Client select",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:23.000000Z",
						"updated_at": "2025-08-20T21:46:23.000000Z"
					}
				},
				{
					"id": 550,
					"custom_field_type_id": 617,
					"company_id": 4,
					"label": "Client select - not  required",
					"placeholder": "Client select - not  required",
					"required": false,
					"type_params": [
						{
							"value": "bla",
							"text": "bla"
						},
						{
							"value": "again",
							"text": "again"
						},
						{
							"value": "new",
							"text": "new"
						}
					],
					"default_value": null,
					"order_on_add_edit_page": 31,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:30:01.000000Z",
					"updated_at": "2025-08-20T22:30:54.000000Z",
					"span": 4,
					"value": "new",
					"error": null,
					"ref": "cf_client_11_clientselectnotrequired",
					"custom_field_type": {
						"id": 617,
						"input_type": "select",
						"input_name": "Client select",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:23.000000Z",
						"updated_at": "2025-08-20T21:46:23.000000Z"
					}
				},
				{
					"id": 551,
					"custom_field_type_id": 618,
					"company_id": 4,
					"label": "Client telephone - required",
					"placeholder": "Client telephone - required",
					"required": true,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 35,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:32:24.000000Z",
					"updated_at": "2025-08-20T22:32:24.000000Z",
					"span": 4,
					"value": "1132131",
					"error": null,
					"ref": "cf_client_12_clienttelephonerequired",
					"custom_field_type": {
						"id": 618,
						"input_type": "telephone",
						"input_name": "Client telephone",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:31.000000Z",
						"updated_at": "2025-08-20T21:46:31.000000Z"
					}
				},
				{
					"id": 552,
					"custom_field_type_id": 618,
					"company_id": 4,
					"label": "Client telephone - not required",
					"placeholder": "Client telephone - not required",
					"required": false,
					"type_params": [],
					"default_value": "56565656",
					"order_on_add_edit_page": 36,
					"order_column_on_index_page": 0,
					"show_on_index_page": 0,
					"deleted_at": null,
					"created_at": "2025-08-20T22:33:02.000000Z",
					"updated_at": "2025-08-20T22:33:02.000000Z",
					"span": 4,
					"value": "56565656",
					"error": null,
					"ref": "cf_client_13_clienttelephonenotrequired",
					"custom_field_type": {
						"id": 618,
						"input_type": "telephone",
						"input_name": "Client telephone",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:31.000000Z",
						"updated_at": "2025-08-20T21:46:31.000000Z"
					}
				},
				{
					"id": 553,
					"custom_field_type_id": 619,
					"company_id": 4,
					"label": "Client text - required",
					"placeholder": "Client text - required",
					"required": true,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 37,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:37:51.000000Z",
					"updated_at": "2025-08-20T22:37:51.000000Z",
					"span": 4,
					"value": "asd",
					"error": null,
					"ref": "cf_client_14_clienttextrequired",
					"custom_field_type": {
						"id": 619,
						"input_type": "text",
						"input_name": "Client text",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:37.000000Z",
						"updated_at": "2025-08-20T21:46:37.000000Z"
					}
				},
				{
					"id": 554,
					"custom_field_type_id": 619,
					"company_id": 4,
					"label": "Client text - not required",
					"placeholder": "Client text - not required",
					"required": false,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 38,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:38:05.000000Z",
					"updated_at": "2025-08-20T22:38:05.000000Z",
					"span": 12,
					"value": "awd",
					"error": null,
					"ref": "cf_client_15_clienttextnotrequired",
					"custom_field_type": {
						"id": 619,
						"input_type": "text",
						"input_name": "Client text",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:37.000000Z",
						"updated_at": "2025-08-20T21:46:37.000000Z"
					}
				},
				{
					"id": 555,
					"custom_field_type_id": 620,
					"company_id": 4,
					"label": "Client textarea - required",
					"placeholder": "Client textarea - required",
					"required": true,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 39,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:38:50.000000Z",
					"updated_at": "2025-08-20T22:38:50.000000Z",
					"span": 12,
					"value": "awdaw",
					"error": null,
					"ref": "cf_client_16_clienttextarearequired",
					"custom_field_type": {
						"id": 620,
						"input_type": "textarea",
						"input_name": "Client textarea",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:43.000000Z",
						"updated_at": "2025-08-20T21:46:43.000000Z"
					}
				},
				{
					"id": 556,
					"custom_field_type_id": 620,
					"company_id": 4,
					"label": "Client textarea - not required",
					"placeholder": "Client textarea - not required",
					"required": false,
					"type_params": [],
					"default_value": "something here",
					"order_on_add_edit_page": 40,
					"order_column_on_index_page": 0,
					"show_on_index_page": 0,
					"deleted_at": null,
					"created_at": "2025-08-20T22:39:10.000000Z",
					"updated_at": "2025-08-20T22:39:10.000000Z",
					"span": 12,
					"value": "something here wade",
					"error": null,
					"ref": "cf_client_17_clienttextareanotrequired",
					"custom_field_type": {
						"id": 620,
						"input_type": "textarea",
						"input_name": "Client textarea",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:43.000000Z",
						"updated_at": "2025-08-20T21:46:43.000000Z"
					}
				},
				{
					"id": 557,
					"custom_field_type_id": 621,
					"company_id": 4,
					"label": "Client time - required",
					"placeholder": "Client time - required",
					"required": true,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 41,
					"order_column_on_index_page": 0,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:39:27.000000Z",
					"updated_at": "2025-08-20T22:39:27.000000Z",
					"span": 6,
					"value": {
						"hours": 1,
						"minutes": 32,
						"seconds": 0
					},
					"error": null,
					"ref": "cf_client_18_clienttimerequired",
					"custom_field_type": {
						"id": 621,
						"input_type": "time",
						"input_name": "Client time",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:48.000000Z",
						"updated_at": "2025-08-20T21:46:48.000000Z"
					}
				},
				{
					"id": 558,
					"custom_field_type_id": 621,
					"company_id": 4,
					"label": "Client time - not required",
					"placeholder": "Client time - not required",
					"required": false,
					"type_params": [],
					"default_value": null,
					"order_on_add_edit_page": 42,
					"order_column_on_index_page": 42,
					"show_on_index_page": 1,
					"deleted_at": null,
					"created_at": "2025-08-20T22:39:49.000000Z",
					"updated_at": "2025-08-20T22:44:33.000000Z",
					"span": 6,
					"value": {
						"hours": 20,
						"minutes": 25,
						"seconds": 2
					},
					"error": null,
					"ref": "cf_client_19_clienttimenotrequired",
					"custom_field_type": {
						"id": 621,
						"input_type": "time",
						"input_name": "Client time",
						"deleted_at": null,
						"created_at": "2025-08-20T21:46:48.000000Z",
						"updated_at": "2025-08-20T21:46:48.000000Z"
					}
				}
			],
			"settings": {
				"currency": {
					"value": "5",
					"error": null
				},
				"payment_terms": {
					"value": "14 Days",
					"error": null
				},
				"quote_valid": {
					"value": "14 Days",
					"error": null
				},
				"send_reminder": {
					"value": "No",
					"error": null
				},
				"size": {
					"value": "4 - 10",
					"error": null
				},
				"industry": {
					"value": "3",
					"error": null
				}
			},
			"company_id": "4"
		}
		*/

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

		return 'all good!';
		/* insert data here */
		
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
			'custom_fields'							=>	'required|array|min:1'
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

						$validation_rules['custom_fields.'.$z.'.value'] = 'required';

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
			'settings.payment_terms.value'							=>	'required',
			'settings.quote_valid.value'							=>	'required',
			'settings.send_reminder.value'							=>	'required',
			'settings.size.value'									=>	'required',
		];

		$settings_validation = Validator::make($request->all(), $settings_rules);
		if($settings_validation->fails()){
			return response($response, config('global.error_code'));
		}

		return null;

	}
	

}
