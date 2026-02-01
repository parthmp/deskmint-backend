<?php

namespace App\Repositories\Currency;

use App\Models\Currency;
use Illuminate\Support\Collection;

class CurrencyRepository{

	/**
	 * fetchSorted function
	 *
	 * @return Collection|null
	 */
	public function fetchSorted() : Collection|null {
		return Currency::orderBy('currency', 'asc')->get();
	}

	/**
	 * fetchWithTextAndValue function
	 *
	 * @return array
	 */
	public function fetchWithTextAndValue() : array {
		return Currency::orderBy('currency', 'asc')->get()->map(function($currency){
			return [
				'value'	=>	$currency->id,
				'text'	=>	$currency->currency.' - '.$currency->code
			];
		});
	}

}