<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\CreateCompanySettingsAddressRequest;
use App\Services\CompanySettingsAddress\CompanySettingsAddressService;
use Exception;
use Illuminate\Http\Request;

class CompanySettingsAddressController extends Controller{

	public function __construct(private CompanySettingsAddressService $company_settings_address_service){

	}

	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{

			$company = $this->company_settings_address_service->fetch($company_id);
			$countries = $this->company_settings_address_service->fetchCountries();

			return [
				'company'		=>		$company,
				'countries'		=>		$countries
			];

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function upsert(CreateCompanySettingsAddressRequest $request){

		try{
			$this->company_settings_address_service->update($request->validated());
			return response(['message' => "Saved successfully", 'validity' => 'saved_success'], 200);
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
