<?php

namespace App\Repositories\Country;

use App\Models\Country;
use Illuminate\Support\Collection;

class CountryRepository{

	/**
	 * fetchSorted function
	 *
	 * @return Collection|null
	 */
	public function fetchSorted() : Collection|null {

		return  Country::orderBy('country_name', 'asc')->get()->map(function($country){
					return [
						'value'	=>	$country->id,
						'text'	=>	$country->country_name,
					];
				});
			
	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return Country|null
	 */
	public function fetchById(int $id) : Country|null {
		return Country::where('id', '=', $id)->first();
	}

}