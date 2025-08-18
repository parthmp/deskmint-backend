<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrenciesControlller extends Controller{
    

	public function fetchCurrencies(Request $request){

		$currencies = Currency::orderBy('currency', 'asc')->get()->map(function($currency){
			return [
				'value'	=>	$currency->id,
				'text'	=>	$currency->currency.' - '.$currency->code
			];
		});

		return $currencies;

	}

}
