<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;

class CountriesController extends Controller{
    
	public function fetchCountries(Request $request){

		$countries = Country::orderBy('country_name', 'asc')->get()->map(function($country){
			return [
				'value'	=>	$country->id,
				'text'	=>	$country->country_name,
			];
		});
		
		return $countries;

	}

}
