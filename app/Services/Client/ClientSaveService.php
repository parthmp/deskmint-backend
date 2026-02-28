<?php

namespace App\Services\Client;

use Illuminate\Http\Request;

class ClientSaveService extends ClientBaseService{
	
	/**
	 * save function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function save(Request $request) : bool {
		return $this->saveOrUpdateClient($request, true);
	}

}