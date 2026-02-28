<?php

namespace App\Services\Client;

use Illuminate\Http\Request;

class ClientUpdateService extends ClientBaseService{
	
	/**
	 * update function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function update(Request $request) : bool {
		return $this->saveOrUpdateClient($request, false);
	}

}