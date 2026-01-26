<?php

namespace App\Modules\CustomFields;

use App\Modules\CustomFields\Actions\Printing;
use Illuminate\Database\Eloquent\Collection;

class CustomFields{

	public function __construct(private Printing $printing){}

	/**
	 * printCustomFields function
	 *
	 * @param Collection $fields
	 * @return \Illuminate\Support\Collection
	 */
	public function printCustomFields(Collection $fields) : \Illuminate\Support\Collection {
		return $this->printing->adjustRowsPrinting($fields);
	}

}