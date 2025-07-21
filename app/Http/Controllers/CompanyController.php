<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompanyController extends Controller{
    
	public function checkCompanyExists(Request $request){

		return [
			'from_laravel' => 'it works'
		];

	}

}
