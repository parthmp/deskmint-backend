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

}