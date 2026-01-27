<?php

namespace App\Modules\CustomFields;

use App\Modules\CustomFields\Actions\Printing;
use App\Modules\CustomFields\Actions\Validation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CustomFields{

	public function __construct(private Printing $printing, private Validation $validation){}

	/**
	 * printCustomFields function
	 *
	 * @param Collection $fields
	 * @return \Illuminate\Support\Collection
	 */
	public function printCustomFields(Collection $fields) : \Illuminate\Support\Collection {
		return $this->printing->adjustRowsPrinting($fields);
	}

	/**
	 * validateCustomFields function
	 *
	 * @param Request $request
	 * @param string $model
	 * @param string $validity
	 * @param integer $tab
	 * @return boolean
	 */
	public function validateCustomFields(Request $request, string $model, string $validity, int $tab = 3) : bool {
		return $this->validation->validateCustomFields($request, $model, $validity, $tab);
	}

}