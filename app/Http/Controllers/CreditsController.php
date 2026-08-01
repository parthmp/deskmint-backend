<?php

namespace App\Http\Controllers;

use App\Exceptions\CreditException;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\Credits\CreditCreateRequest;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Services\Credit\CreditService;
use Exception;
use Illuminate\Http\Request;

class CreditsController extends Controller {

	private array $additional_fields = [
		[
			'label'			=>	'c_code',
			'text'			=>	'Currency'
		],
		[
			'label'			=>	'full_name',
			'text'			=>	'Name'
		],
		[
			'label'			=>	'applied_amount',
			'text'			=>	'Applied'
		],
		[
			'label'			=>	'amount_left_to_be_applied',
			'text'			=>	'Amount left'
		]
		
	];

	private array $date_fields = [
		'created_at'
	];

	public function __construct(
		private CreditService $credit_service,
		private ArrangedDataTableColumns $arranged_data_table_columns
	){}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'credits', 'credits', null, 'credit', remove_columns:['deleted_at', 'updated_at', 'company_id', 'amount_left_to_be_applied', 'applied_amount', 'client_id', 'currency_id'], additional_fields: $this->additional_fields);
	}
	

	public function saveArrangedColumns(Request $request){

		try{
			$this->arranged_data_table_columns->saveArrangedColumnsData($request, null, 'credits', 'credits', 'credit', $this->additional_fields, $this->date_fields);
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(InvalidDataProvidedException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function store(CreditCreateRequest $request){

		$data = $request->validated();

		try{

			$this->credit_service->create((int) $data['company_id'], (int) $data['client_id'], (string) $data['amount']);

			return response(['message' => 'Credit created successfully', 'validity' => 'credit_created'], 200);

		}catch(Exception $e){

			return General::wentWrong();

		}
		

	}

	public function index(Request $request){

		try{
			return $this->credit_service->fetchIndex($request);
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

			$this->credit_service->deleteCredits($ids);
			return response(['message' => 'Credit(s) deleted successfully', 'validity' => 'delete_success'], 200);

		}catch(CreditException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

}
