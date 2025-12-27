<?php

namespace App\Http\Controllers;

use App\Services\Currency\CurrencyService;
use Illuminate\Http\Request;

class CurrenciesControlller extends Controller{
    
	public function __construct(private CurrencyService $currency_service){
	}

	public function fetchCurrencies(Request $request){
		
		return $this->currency_service->fetchAll();

	}

}
