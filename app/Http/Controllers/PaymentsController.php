<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;

class PaymentsController extends Controller {
    
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
		private PaymentService $payment_service,
		private ArrangedDataTableColumns $arranged_data_table_columns
	){}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'payments', 'payments', null, 'payment', remove_columns:['deleted_at', 'updated_at', 'company_id', 'amount_left_to_be_applied', 'applied_amount', 'client_id', 'currency_id'], additional_fields: $this->additional_fields);
	}
	

	public function saveArrangedColumns(Request $request){

		try{
			$this->arranged_data_table_columns->saveArrangedColumnsData($request, null, 'payments', 'payments', 'payment', $this->additional_fields, $this->date_fields);
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(InvalidDataProvidedException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function index(Request $request){
		return $this->payment_service->fetchIndex($request);
	}

}
