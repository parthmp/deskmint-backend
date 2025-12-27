<?php

namespace App\Http\Controllers;

use App\Services\Country\CountryService;
use Illuminate\Http\Request;

class CountriesController extends Controller{

	public function __construct(private CountryService $country_service){
	}
    
	public function fetchCountries(Request $request){
		return $this->country_service->fetchAll();
	}

}
