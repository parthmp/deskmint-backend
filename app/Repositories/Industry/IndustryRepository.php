<?php

namespace App\Repositories\Industry;

use App\Models\Industry;
use Illuminate\Support\Collection;

class IndustryRepository{

	/**
	 * fetchSorted function
	 *
	 * @return Collection|null
	 */
	public function fetchSorted() : Collection|null {
		return Industry::orderBy('industry_name', 'asc')->get();
	}

}