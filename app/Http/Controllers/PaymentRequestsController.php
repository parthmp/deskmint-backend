<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentRequestException;
use App\Helpers\General;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\PaymentRequest\CreateEditPaymentRequestRequest;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Modules\DataTable\Requests\DataTableRequest;
use App\Services\PaymentRequest\PaymentRequestService;
use Exception;
use Illuminate\Http\Request;

class PaymentRequestsController extends Controller {

	private array $additional_fields = [
		[
			'label'			=>	'c_code',
			'text'			=>	'Currency'
		],
		[
			'label'			=>	'token_id_identifier',
			'text'			=>	'Token'
		],
		[
			'label'			=>	'full_name',
			'text'			=>	'Full name'
		]
		
	];

	private array $date_fields = [
		'created_at',
		'sent_at'
	];

	public function __construct(
		private PaymentRequestService $payment_request_service,
		private ArrangedDataTableColumns $arranged_data_table_columns
	){}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'payment_requests', 'payment_requests', null, 'payment_request', remove_columns:['company_id', 'token_id_identifier', 'client_id', 'transaction_id', 'deleted_at', 'updated_at', 'currency_id'], additional_fields: $this->additional_fields);
	}
	

	public function saveArrangedColumns(Request $request){

		try{
			$this->arranged_data_table_columns->saveArrangedColumnsData($request, null, 'payment_requests', 'payment_requests', 'payment_request', $this->additional_fields, $this->date_fields);
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(InvalidDataProvidedException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}


	public function fetchInit(GenericRequest $request){
		$data = $request->validated();
		return $this->payment_request_service->fetchInit((int) $data['company_id']);
	}

	public function store(CreateEditPaymentRequestRequest $request){

		$data = $request->validated();
		
		//try{

			$payment_request = $this->payment_request_service->create($data);
			
			if((bool) $data['send_send_request']){
				$this->payment_request_service->sendRequest((int) $data['company_id'], (int) $payment_request->id);
			}

			return response(['message' => 'Payment request created successfully', 'validity' => 'payment_request_created'], 200);

		// }catch(PaymentRequestException $e){
		// 	return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		// }catch(Exception $e){
		// 	return General::wentWrong();
		// }
		
	}

	public function index(DataTableRequest $request){

		$data = $request->validated();
		
		return $this->payment_request_service->fetch($request);

	}
	

}
