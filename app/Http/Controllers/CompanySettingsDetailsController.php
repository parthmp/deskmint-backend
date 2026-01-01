<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\CompanySettingsDetails\CreateCompanySettingsDetailsRequest;
use App\Http\Requests\GenericRequest;
use App\Services\CompanySettingsDetails\CompanySettingsDetailsService;
use Exception;

class CompanySettingsDetailsController extends Controller{

	public function __construct(private CompanySettingsDetailsService $company_settings_details_service){

	}
    
	public function show(GenericRequest $request){

		$data = $request->validated();
		$company_id = $data['company_id'];

		try{

			$data = $this->company_settings_details_service->fetch($company_id);
			if($data){
				return $data;
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function upsert(CreateCompanySettingsDetailsRequest $request){

		try{
			
			if($this->company_settings_details_service->updateCompanyDetails($request->validated())){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
