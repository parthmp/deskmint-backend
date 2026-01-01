<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\CompanySettingsAddress\CreateCompanySettingsAddressRequest;
use App\Http\Requests\GenericRequest;
use App\Services\CompanySettingsAddress\CompanySettingsAddressService;
use Exception;

class CompanySettingsAddressController extends Controller{

	public function __construct(private CompanySettingsAddressService $company_settings_address_service){

	}

	public function show(GenericRequest $request){

		$data = $request->validated();
		$company_id = $data['company_id'];
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
