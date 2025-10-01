<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Models\Country;
use Illuminate\Http\Request;

class CountriesController extends Controller{
    
	public function fetchCountries(Request $request){

		return General::fetchCoutries();

	}

}
