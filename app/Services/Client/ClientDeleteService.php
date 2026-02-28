<?php

namespace App\Services\Client;

use App\Repositories\Client\ClientRepository;
use App\Services\Client\Exceptions\ClientException;
use Exception;
use Illuminate\Support\Facades\Schema;

/**
 * ClientDeleteService class
 */
class ClientDeleteService{

	public function __construct(private ClientRepository $client_repository){}

	public function deleteClientsByIds(array $ids) : bool {

		if(empty($ids)){
			throw new ClientException('No valid IDs provided', 'invalid_ids', config('global.error_code'));
		}

		foreach($ids as $id){
			if(!is_numeric($id)){
				throw new ClientException('All IDs must be numeric', 'non_numeric', config('global.error_code'));
			}
		}

		try{

			$flat_table = 'clients_flat';

			$this->client_repository->deleteRecordsByClientIds($flat_table, $ids);
			
			return true;

		}catch(Exception $e){
			throw new ClientException('Something went wrong', 'something_wrong', 500);
		}

	}

}