<?php

namespace App\Http\Controllers;

use App\Services\Industry\IndustryService;
use Illuminate\Http\Request;

class IndustriesController extends Controller{

	public function __construct(private IndustryService $industry_service){

	}
    
	public function fetchIndustries(Request $request){

		return $this->industry_service->fetchAll();

	}

}
