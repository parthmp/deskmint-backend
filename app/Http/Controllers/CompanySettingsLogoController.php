<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\CompanySettingsLogo\CreateCompanySettingsLogoRequest;
use App\Services\CompanySettingsLogo\CompanySettingsLogoService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompanySettingsLogoController extends Controller{


	public function __construct(private CompanySettingsLogoService $company_settings_logo_service){
		
	}


	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$url = $this->company_settings_logo_service->fetch($company_id);

		return [
			'url'	=>	$url
		];

	}
    
	public function upsert(CreateCompanySettingsLogoRequest $request){

		$data = $request->validated();

		$this->company_settings_logo_service->updateCompanyLogo([
			'company_id'	=>	$data['company_id'],
			'logo'			=>	$request->file('logo')
		]);

	}

	public function destroy(Request $request){
		
		$company_id = Sanitize::input($request->input('company_id'));

		try{

			$path = 'logos/'.$company_id;

			if(Storage::disk('public')->exists($path)){
				Storage::disk('public')->deleteDirectory($path);
			}

			$company = General::fetchDefaultCompanyById($company_id);
			$company->logo = '';

			if($company->save()){
				return response(['message' => 'Logo removed successfully', 'validity' => 'remove_success'], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
