<?php

namespace App\Repositories\Country;

use App\Models\Country;
use Illuminate\Support\Collection;

class CountryRepository{

	public function fetchSorted() : Collection|null {

		return  Country::orderBy('country_name', 'asc')->get()->map(function($country){
					return [
						'value'	=>	$country->id,
						'text'	=>	$country->country_name,
					];
				});
			
	}

	public function fetchById(int $id) : Country|null {
		return Country::where('id', '=', $id)->first();
	}

}