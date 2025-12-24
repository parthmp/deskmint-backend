<?php

namespace App\Repositories\CompanySettingsAdditionalFields;

use App\Models\AdditionalCompanyField;
use App\Models\Company;
use App\Repositories\Company\CompanyRepository;

class CompanySettingsAdditionalFieldsRepository{

	public function __construct(private CompanyRepository $company_repository){
	}

	/**
	 * fetchDefaulyByComapnyId function
	 *
	 * @param integer $company_id
	 * @return Company|null
	 */
	public function fetchDefaulyByComapnyId(int $company_id) : Company|null {
		return $this->company_repository->fetchDefaultById($company_id);
	}

	/**
	 * upsert function
	 *
	 * @param array $data
	 * @return void
	 */
	public function upsert(array $data){
		AdditionalCompanyField::upsert($data, ['id'], ['label', 'value']);
	}
	
	/**
	 * fetchByIdWithCompanyId function
	 *
	 * @param integer $id
	 * @param integer $company_id
	 * @return AdditionalCompanyField|null
	 */
	public function fetchByIdWithCompanyId(int $id, int $company_id) : AdditionalCompanyField|null {
		return AdditionalCompanyField::where([['id', '=', $id], ['company_id', '=', $company_id]])->first();
	}

}