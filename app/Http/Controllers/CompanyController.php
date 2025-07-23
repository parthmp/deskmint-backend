<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller{
    
	public function checkCompanyExists(Request $request){

		$company = Company::where('default', '=', 1)->first();

		$company_exists = false;
		$company_id = null;

		if($company){
			$company_exists = true;
			$company_id = $company->id;
		}

		return [
			'company_exists' 	=> 	$company_exists,
			'company_id'		=>	$company_id
		];

	}

	public function setDefaultCompany(Request $request){
		
		$v = Validator::make($request->all(), [
			'company_name'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		try{
			$company_id = (new CompanyService())->addNewCompany($request->input('company_name'), true);
			return response(['message' => 'Company added successfully', 'company_id' => $company_id, 'validity' => 'success'], 200);
		}catch(Exception $e){
			return response(['message' => 'Unknown error'], 500);
		}

	}

}
