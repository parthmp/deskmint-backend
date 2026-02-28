<?php

namespace App\Services\Client;

use Illuminate\Http\Request;

class ClientService{

	public function __construct(
		private ClientFetchService $client_fetch_service, 
		private ClientDeleteService $client_delete_service,
		private ClientSaveService $client_save_service,
		private ClientUpdateService $client_update_service
	){}

	/**
	 * fetchCustomFields function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchCustomFields(Request $request) : array {
		return $this->client_fetch_service->fetchCustomFields($request);
	}


	/**
	 * fetchIndex function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchIndex(Request $request) : array {
		return $this->client_fetch_service->fetchIndex($request);
	}

	/**
	 * deleteClients function
	 *
	 * @param array $ids
	 * @return boolean
	 */
	public function deleteClients(array $ids) : bool {
		return $this->client_delete_service->deleteClientsByIds($ids);
	}

	/**
	 * fetchSingleClientById function
	 *
	 * @param integer $id
	 * @return array
	 */
	public function fetchSingleClientById(int $id) : array {
		return $this->client_fetch_service->fetchSingleClientById($id);
	}

	/**
	 * save function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function save(Request $request) : bool {
		return $this->client_save_service->save($request);
	}

	/**
	 * update function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function update(Request $request) : bool {
		return $this->client_update_service->update($request);
	}

}