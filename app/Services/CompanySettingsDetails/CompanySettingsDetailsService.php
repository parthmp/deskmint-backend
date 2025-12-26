<?php

namespace App\Services\CompanySettingsDetails;

use App\Models\Company;
use App\Repositories\Company\CompanyRepository;
use Exception;

class CompanySettingsDetailsService{

	public function __construct(private CompanyRepository $company_repository){

	}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @return Company|null
	 */
	public function fetch(int $company_id) : Company|null {

		$company = $this->company_repository->fetchDefaultById($company_id);

		if(!$company){
			throw new Exception('can not find the company');
		}

		return $company;

	}

	/**
	 * updateCompanyDetails function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function updateCompanyDetails(array $data) : bool {

		$company = $this->fetch((int) $data['company_id']);

		return $this->company_repository->updateCompanyDetailsByObj($data, $company);

	}

}