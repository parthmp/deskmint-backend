<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\CompanySettingsLogo\CreateCompanySettingsLogoRequest;
use App\Http\Requests\GenericRequest;
use App\Services\CompanySettingsLogo\CompanySettingsLogoService;
use Exception;
use Illuminate\Http\Response;

class CompanySettingsLogoController extends Controller{


	public function __construct(private CompanySettingsLogoService $company_settings_logo_service){
		
	}

	
	public function show(GenericRequest $request) : array {

		$data = $request->validated();
		$company_id = $data['company_id'];

		$url = $this->company_settings_logo_service->fetch($company_id);

		return [
			'url'	=>	$url
		];

	}
	
	
	public function upsert(CreateCompanySettingsLogoRequest $request) : Response {

		$data = $request->validated();

		try{
			
			if($this->company_settings_logo_service->update(['company_id' => $data['company_id'], 'logo' => $request->file('logo')])){
				return response(['message' => 'Logo saved successfully', 'validity' => 'upload_success'], 200);
			}
			
			return General::wentWrong();
			
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	
	public function destroy(GenericRequest $request) : Response {
		
		$data = $request->validated();
		$company_id = $data['company_id'];

		try{

			if($this->company_settings_logo_service->remove($company_id)){
				return response(['message' => 'Logo removed successfully', 'validity' => 'remove_success'], 200);
			}
			
			return General::wentWrong();
			
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
