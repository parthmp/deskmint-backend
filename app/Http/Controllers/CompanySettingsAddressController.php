<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanySettingsAddressController extends Controller{

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$company = General::fetchDefaultCompanyById($company_id);

		if(!$company){
			return response(['message' => 'invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return $company;


	}

}
