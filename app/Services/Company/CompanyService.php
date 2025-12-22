<?php

namespace App\Services\Company;

use App\Helpers\Sanitize;
use App\Repositories\Company\CompanyRepository;
use Exception;

class CompanyService{

	public function __construct(private CompanyRepository $company_repository){

	}

	/**
	 * checkCompanyExistsWithData function
	 *
	 * @return array
	 */
	public function checkCompanyExistsWithData() : array {
		
		$company = $this->company_repository->fetchDefault();

		$company_exists = false;
		$company_id = null;

		if($company){
			$company_exists = true;
			$company_id = $company->id;
		}

		return [
			'company_exists' 	=> 	$company_exists,
			'company_id'		=>	$company_id
		];

	}

	/**
	 * setDefaultCompany function
	 *
	 * @param array $data
	 * @return integer
	 */
	public function setDefaultCompany(array $data) : int {

		try{

			$company_name = (string) Sanitize::input($data['company_name']);

			return $this->company_repository->create($company_name, true);

			
		}catch(Exception $e){
			throw new Exception('failed to set default company');
		}

	}

}