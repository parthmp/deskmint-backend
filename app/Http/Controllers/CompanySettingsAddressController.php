<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Company;
use App\Models\Country;
use Exception;
use Illuminate\Http\Request;

class CompanySettingsAddressController extends Controller{

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$company = General::fetchDefaultCompanyById($company_id);

		if(!$company){
			return response(['message' => 'invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$countries = General::fetchCoutries();

		return [
			'company'		=>		$company,
			'countries'		=>		$countries
		];


	}

	public function saveOrUpdate(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		try{

			$company = General::fetchDefaultCompanyById($company_id);
			
			if(!$company){
				return response(['message' => 'invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}

			$street = '';

			if($request->filled('address_street')){
				$street = Sanitize::input($request->input('address_street'));
			}

			$apt = '';
			if($request->filled('apt')){
				$apt = Sanitize::input($request->input('apt'));
			}

			$city = '';
			if($request->filled('city')){
				$city = Sanitize::input($request->input('city'));
			}

			$state = '';
			if($request->filled('state')){
				$state = Sanitize::input($request->input('state'));
			}

			$postal_code = '';
			if($request->filled('postal_code')){
				$postal_code = Sanitize::input($request->input('postal_code'));
			}

			$country_id = null;

			if($request->filled('country_id')){
				
				$country_temp_id = Sanitize::input($request->input('country_id'));
				$country = Country::where('id', '=', $country_temp_id)->first();
				
				if(!$country){
					return response(['message' => "Invalid request", 'validity' => 'invalid_data'], config('global.error_code'));
				}

				$country_id = $country_temp_id;
				$country_temp_id = null;

			}

			$company->address_street = $street;
			$company->apt = $apt;
			$company->city = $city;
			$company->state = $state;
			$company->postal_code = $postal_code;
			$company->country_id = $country_id;

			if($company->save()){
				return response(['message' => "Saved successfully", 'validity' => 'saved_success'], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
