<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanySettingsAdditionalFieldsController extends Controller{
    
	public function saveOrUpdate(Request $request){

		$v = Validator::make($request->all(), [
			'all_fields'			=>	'required|array',
			'all_fields.*.label'	=>	'required',
			'all_fields.*.value'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_data'], config('global.error_code'));
		}

		$all_field = $request->input('all_fields');
		

	}

}
