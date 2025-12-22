<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\Company\SetDefaultCompanyRequest;
use App\Services\Company\CompanyService;
use Exception;
use Illuminate\Http\Request;

class CompanyController extends Controller{

	public function __construct(private CompanyService $company_service){

	}
    
	/**
	 * checkCompanyExists function
	 *
	 * @param Request $request
	 * @return mixed
	 */
	public function checkCompanyExists(Request $request){
		return $this->company_service->checkCompanyExistsWithData();
	}

	/**
	 * setDefaultCompany function
	 *
	 * @param SetDefaultCompanyRequest $request
	 * @return void
	 */
	public function setDefaultCompany(SetDefaultCompanyRequest $request){

		try{

			$company_id = $this->company_service->setDefaultCompany($request->validated());
			return response(['message' => 'Company added successfully', 'company_id' => $company_id, 'validity' => 'success'], 200);

		}catch(Exception $e){
			General::wentWrong();
		}

	}

}
