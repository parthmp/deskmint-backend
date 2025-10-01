<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanySettingsDetailsController extends Controller{
    
	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$company = General::fetchDefaultCompanyById($company_id);

		if(!$company){
			return response(['message' => 'invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return $company;

	}

	public function saveOrUpdate(Request $request){

		$v = Validator::make($request->all(), [
			'company_name'	=>	'required'
		]);

		$company_id = Sanitize::input($request->input('company_id'));
		$company_name = Sanitize::input($request->input('company_name'));

		$size = '';
		if($request->filled('size')){
			$size = Sanitize::input($request->input('size'));
		}
		
		$id_number = '';
		if($request->filled('id_number')){
			$id_number = Sanitize::input($request->input('id_number'));
		}

		$gst = '';
		if($request->filled('gst')){
			$gst = Sanitize::input($request->input('gst'));
		}

		$classification = '';
		if($request->filled('classification')){
			$classification = Sanitize::input($request->input('classification'));
		}

		$website = '';
		if($request->filled('website')){
			$website = Sanitize::input($request->input('website'));
		}

		$email = '';
		if($request->filled('email')){
			$email = Sanitize::input($request->input('email'));
		}

		$phone = '';
		if($request->filled('phone')){
			$phone = Sanitize::input($request->input('phone'));
		}

		$company = Company::where([['id', '=', $company_id], ['default', '=', 1]])->first();

		$company->company_name = $company_name;
		$company->size = $size;
		$company->id_number = $id_number;
		$company->gst_vat_number = $gst;
		$company->classification = $classification;
		$company->website = $website;
		$company->email = $email;
		$company->phone = $phone;
		
		if($company->save()){
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}


	}

}
