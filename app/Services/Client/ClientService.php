<?php

namespace App\Services\Client;

use Illuminate\Http\Request;

class ClientService{

	public function __construct(private ClientFetchService $client_fetch_service){}

	/**
	 * fetchCustomFields function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchCustomFields(Request $request) : array {
		return $this->client_fetch_service->fetchCustomFields($request);
	}

}