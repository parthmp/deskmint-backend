<?php

namespace App\Modules\ArrangedDataTableColumns\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Validation{

	public function validatePostedColumns(Request $request){

		$validation_rules = [
			'columns'	 =>	'required|array'
		];

		$v = Validator::make($request->all(), $validation_rules);

		if($v->fails()){
			//return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return null;

	}

}