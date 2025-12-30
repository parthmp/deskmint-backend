<?php

namespace App\Services\Industry;

use App\Repositories\Industry\IndustryRepository;
use Illuminate\Support\Collection;

/**
 * IndustryService class
 */
class IndustryService{

	public function __construct(private IndustryRepository $industry_repository){
	}

	/**
	 * fetchAll function
	 *
	 * @return Collection
	 */
	public function fetchAll() : Collection {

		$industries = $this->industry_repository->fetchSorted()->map(function($ind){
			return [
				'value'	=>	$ind->id,
				'text'	=>	$ind->industry_name
			];
		});

		return $industries;
	}
}