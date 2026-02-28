<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Services\Client\ClientService;
use App\Services\Client\Exceptions\ClientException;
use Exception;
use Illuminate\Http\Request;

class ClientsController extends Controller{

	public function __construct(private ArrangedDataTableColumns $arranged_data_table_columns, private ClientService $client_service){}

	public function fetchClientsCustomFields(Request $request){

		return $this->client_service->fetchCustomFields($request);

	}

	public function store(Request $request){

		try{
			$this->client_service->save($request);
		}catch(ClientException $e){
			return response($e->getMessage(), $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

	public function index(Request $request){
		
		try{
			return $this->client_service->fetchIndex($request);
		}catch(ClientException $e){
			return response($e->getMessage(), $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'clients', 'clients', ClientsCustomField::class, 'client');
	}
	

	public function saveArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->saveArrangedColumnsData($request, ClientsCustomField::class, 'clients', 'clients', 'client');
	}

	public function destroy(Request $request){
		
		$ids = $request->input('ids');
		
		try{
			$ids = Sanitize::recursive($ids);
			$this->client_service->deleteClients($ids);
		}catch(ClientException $e){
			return response($e->getMessage(), $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function show(Request $request, int $id){
		
		try{

			$id = Sanitize::input($id);

			return $this->client_service->fetchSingleClientById($id);

		}catch(ClientException $e){
			return response($e->getMessage(), $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function update(Request $request){
		try{
			$this->client_service->update($request);
		}catch(ClientException $e){
			return response($e->getMessage(), $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
	}

}
