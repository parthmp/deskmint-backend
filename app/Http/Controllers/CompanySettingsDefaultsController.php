<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\CompanySettingsDefault\CreateCompanySettingsDefaultRequest;
use App\Services\CompanySettingsDefaults\CompanySettingsDefaultsService;
use Exception;
use Illuminate\Http\Request;

class CompanySettingsDefaultsController extends Controller{

	public function __construct(private CompanySettingsDefaultsService $company_settings_defaults_service){

	}

	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{

			$company = $this->company_settings_defaults_service->fetch($company_id);

			return [
				'invoice_terms' 	=> $company->invoice_terms,
				'invoice_footer'	=> $company->invoice_footer,
			];

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function upsert(CreateCompanySettingsDefaultRequest $request){

		try{

			if($this->company_settings_defaults_service->update($request->validated())){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
