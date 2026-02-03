<?php

namespace App\Services\Client;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * ClientValidationService class
 */
class ClientValidationService{

	/**
	 * validateForIndex function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateForIndex(Request $request) : bool {

		$v = Validator::make($request->all(), [
			'default_per_page'	=>	'required|integer|min:1'
		]);

		return !$v->fails();

	}

}