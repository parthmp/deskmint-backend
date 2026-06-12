<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Modules\CustomFields\Exceptions\InvalidCustomFieldsException;
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
			return response(['message' => 'Client saved successfully', 'validity' => 'client_saved'], 200);
		}catch(ClientException|InvalidCustomFieldsException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

	public function index(Request $request){
		
		//try{
			return $this->client_service->fetchIndex($request);
		// }catch(ClientException $e){
		// 	return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		// }catch(Exception $e){
		// 	return General::wentWrong();
		// }
		
	}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'clients', 'clients', ClientsCustomField::class, 'client');
	}
	

	public function saveArrangedColumns(Request $request){

		try{
			$this->arranged_data_table_columns->saveArrangedColumnsData($request, ClientsCustomField::class, 'clients', 'clients', 'client');
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(InvalidDataProvidedException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function destroy(Request $request){
		
		$ids = $request->input('ids');

		if(!$ids){
			return response(['message' => 'No valid IDs provided', 'validity' => 'invalid_ids'], config('global.error_code'));
		}
		
		try{
			$ids = Sanitize::recursive($ids);
			$this->client_service->deleteClients($ids);
			return response(['message' => 'Client(s) deleted successfully', 'validity' => 'delete_success'], 200);
		}catch(ClientException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function show(Request $request, int $id){
		
		try{

			$id = Sanitize::input($id);

			return $this->client_service->fetchSingleClientById($id);

		}catch(ClientException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function update(Request $request){
		try{
			$this->client_service->update($request);
			return response(['message' => 'Client updated successfully', 'validity' => 'client_updated'], 200);
		}catch(ClientException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity(), 'tab_switch' => $e->getTab()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
	}

}
