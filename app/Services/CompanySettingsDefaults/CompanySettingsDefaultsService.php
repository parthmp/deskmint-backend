<?php

namespace App\Services\CompanySettingsDefaults;

use App\Models\Company;
use App\Repositories\Company\CompanyRepository;
use Exception;

class CompanySettingsDefaultsService{

	public function __construct(private CompanyRepository $company_repository){

	}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @return Company|null
	 */
	public function fetch(int $company_id) : Company|null {
		return $this->company_repository->fetchDefaultById($company_id);
	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function update(array $data) : bool {

		$company_id = $data['company_id'];

		try{

			$company = $this->fetch($company_id);
			$company->invoice_terms = $data['invoice_terms'];
			$company->invoice_footer = $data['invoice_footer'];

			return $company->save();

		}catch(Exception $e){
			throw new Exception('failed to update terms and footer for company');
		}

	}

}