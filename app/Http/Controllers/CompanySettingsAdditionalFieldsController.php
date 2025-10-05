<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanySettingsAdditionalFieldsController extends Controller{
    
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

}
