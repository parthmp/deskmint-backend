<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\CompanySettingsAdditionalFields\CreateCompanySettingsAdditionalFieldsRequest;
use App\Http\Requests\CompanySettingsAdditionalFields\DestroyCompanySettingsAdditionalFieldsRequest;
use App\Http\Requests\GenericRequest;
use App\Services\CompanySettingsAdditionalFields\CompanySettingsAdditionalFieldsService;
use Exception;

class CompanySettingsAdditionalFieldsController extends Controller{

	public function __construct(private CompanySettingsAdditionalFieldsService $company_settings_additional_fields_service){
		
	}

	public function show(GenericRequest $request){

		try{
			$data = $request->validated();
			$company_id = $data['company_id'];
			return $this->company_settings_additional_fields_service->fetch($company_id);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function upsert(CreateCompanySettingsAdditionalFieldsRequest $request){
		
		try{
			$this->company_settings_additional_fields_service->update($request->validated());
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

	public function destroy(DestroyCompanySettingsAdditionalFieldsRequest $request){

		try{
			$this->company_settings_additional_fields_service->destroy($request->validated());
			return response(['message' => 'Deleted successfully', 'validity' => 'deleted_success'], 200);
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
