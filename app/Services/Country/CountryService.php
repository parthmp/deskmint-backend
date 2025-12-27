<?php

namespace App\Services\Country;

use App\Repositories\Country\CountryRepository;
use Illuminate\Support\Collection;

/**
 * CountryService class
 */
class CountryService{

	/**
	 * __construct function
	 *
	 * @param CountryRepository $country_repository
	 */
	public function __construct(private CountryRepository $country_repository){
	}

	/**
	 * fetchAll function
	 *
	 * @return Collection
	 */
	public function fetchAll() : Collection {
		return $this->country_repository->fetchSorted();
	}

}