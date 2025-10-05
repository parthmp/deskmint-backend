<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanySettingsAdditionalFieldsController extends Controller{

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));
		$company = General::fetchDefaultCompanyById($company_id);

		$fields = $company->additional_fields->toArray() ? $company->additional_fields->toArray() : [];

		return array_reverse($fields);

	}

    
	public function saveOrUpdate(Request $request){

		$v = Validator::make($request->all(), [
			'all_fields'			=>	'required|array',
			'all_fields.*.label'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_data'], config('global.error_code'));
		}

		try{

			$all_fields = $request->input('all_fields');
			$company_id = Sanitize::input($request->input('company_id'));
			
			$upsert = [];

			foreach($all_fields  as $field){
				
				$element = [];

				if(isset($field['id']) && $field['id']){

					$field_id = Sanitize::input($field['id']);

					if($field_id !== ''){
						$element['id'] = $field_id;
					}

				}else{
					$element['id'] = null;
				}

				$element['company_id'] = $company_id;
				$element['label'] = Sanitize::input($field['label']);
				$element['value'] = Sanitize::input(($field['value']) ? $field['value'] : '');

				if(!empty($element)){
					array_push($upsert, $element);
				}

			}

			AdditionalCompanyField::upsert($upsert, ['id'], ['label', 'value']);

			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function destroy(Request $request){

		$v = Validator::make($request->all(), [
			'id'			=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		try{

			$company_id = Sanitize::input($request->input('company_id'));
			$id = Sanitize::input($request->input('id'));
			$additonal_field = AdditionalCompanyField::where([['id', '=', $id], ['company_id', '=', $company_id]])->first();

			if(!$additonal_field){
				return response(['message' => 'Invalid data', 'validity' => 'invalid_data'], config('global.error_code'));
			}

			if($additonal_field->delete()){
				return response(['message' => 'Deleted successfully', 'validity' => 'deleted_success'], 200);
			}else{
				return General::wentWrong();
			}

		}catch(Exception $e){
			return General::wentWrong();
		}
		

	}

}
