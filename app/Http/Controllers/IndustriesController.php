<?php

namespace App\Http\Controllers;

use App\Models\Industry;
use Illuminate\Http\Request;

class IndustriesController extends Controller{
    
	public function fetchIndustries(Request $request){

		$industries = Industry::orderBy('industry_name', 'asc')->get()->map(function($ind){
			return [
				'value'	=>	$ind->id,
				'text'	=>	$ind->industry_name
			];
		});

		return $industries;

	}

}
