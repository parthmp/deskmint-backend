<?php

namespace App\Repositories\Industry;

use App\Models\Industry;
use Illuminate\Support\Collection;

class IndustryRepository{

	/**
	 * fetchSorted function
	 *
	 * @return Collection|null
	 */
	public function fetchSorted() : Collection|null {
		return Industry::orderBy('industry_name', 'asc')->get();
	}
	
	/**
	 * fetchWithTextAndValue function
	 *
	 * @return array
	 */
	public function fetchWithTextAndValue() : array {
		return Industry::orderBy('industry_name', 'asc')->get()->map(function($ind){
			return [
				'value'	=>	$ind->id,
				'text'	=>	$ind->industry_name
			];
		});
	}

}