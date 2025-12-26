<?php

namespace App\Services\CompanySettingsAddress;

use App\Models\Company;
use App\Repositories\Company\CompanyRepository;
use App\Repositories\Country\CountryRepository;
use Exception;
use Illuminate\Support\Collection;

class CompanySettingsAddressService{

	public function __construct(private CompanyRepository $company_repository, private CountryRepository $country_repository){

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
	 * fetchCountries function
	 *
	 * @return Collection|null
	 */
	public function fetchCountries() : Collection|null {
		return $this->country_repository->fetchSorted();
	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @return void
	 */
	public function update(array $data){

		$company = $this->fetch((int) $data['company_id']);

		if($data['country_id']){

			$country = $this->country_repository->fetchById($data['country_id']);
			
			if(!$country){
				throw new Exception('unable to find country');
			}

		}

		$this->company_repository->updateCompanyAddressByObj($data, $company);
		
	}

}