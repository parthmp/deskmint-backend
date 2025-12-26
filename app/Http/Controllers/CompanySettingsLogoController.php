<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\CompanySettingsLogo\CreateCompanySettingsLogoRequest;
use App\Services\CompanySettingsLogo\CompanySettingsLogoService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompanySettingsLogoController extends Controller{


	public function __construct(private CompanySettingsLogoService $company_settings_logo_service){
		
	}

	/**
	 * show function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function show(Request $request) : array {

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$url = $this->company_settings_logo_service->fetch($company_id);

		return [
			'url'	=>	$url
		];

	}
	
	/**
	 * upsert function
	 *
	 * @param CreateCompanySettingsLogoRequest $request
	 * @return Response
	 */
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

	/**
	 * destroy function
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function destroy(Request $request) : Response {
		
		$company_id = (int) Sanitize::input($request->input('company_id'));

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
