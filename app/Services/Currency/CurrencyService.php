<?php

namespace App\Services\Currency;

use App\Repositories\Currency\CurrencyRepository;
use Illuminate\Support\Collection;

class CurrencyService{

	public function __construct(private CurrencyRepository $currency_repository){
	}

	/**
	 * fetchAll function
	 *
	 * @return Collection
	 */
	public function fetchAll() : Collection {

		$currencies = $this->currency_repository->fetchSorted()->map(function($currency){
			return [
				'value'	=>	$currency->id,
				'text'	=>	$currency->currency.' - '.$currency->code
			];
		});

		return $currencies;
	}

}