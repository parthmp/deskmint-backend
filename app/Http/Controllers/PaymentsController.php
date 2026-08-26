<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\Payments\PaymentCreateRequest;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;

class PaymentsController extends Controller {
    
	/**
	 * additional_fields
	 *
	 * @var array
	 */
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
		],
		[
			'label'			=>	'received_amount',
			'text'			=>	'Received'
		],
		[
			'label'			=>	'gateway_fees_amount',
			'text'			=>	'Fee'
		],
		[
			'label'			=>	'payment_gateway',
			'text'			=>	'Gateway'
		],
		[
			'label'			=>	'token_id_identifier',
			'text'			=>	'Token'
		],
		[
			'label'			=>	'payment_type_n',
			'text'			=>	'Payment type'
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
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'payments', 'payments', null, 'payment', remove_columns:['deleted_at', 'updated_at', 'company_id', 'amount_left_to_be_applied', 'applied_amount', 'client_id', 'currency_id', 'transaction_id', 'payment_type_id'], additional_fields: $this->additional_fields);
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

	public function store(PaymentCreateRequest $request){

		$data = $request->validated();

		try{

			$this->payment_service->create((int) $data['company_id'], (int) $data['client_id'], (string) $data['amount'], (int) $data['payment_type']);

			return response(['message' => 'Payment created successfully', 'validity' => 'payment_created'], 200);

		}catch(Exception $e){

			return General::wentWrong();

		}
		

	}

	public function show(GenericRequest $request, int $id){

		$mode = Sanitize::input($request->segment(3));
		$id = (int) Sanitize::input($id);
		$data = $request->validated();


		$applied_entries = [];

		if($mode === 'view'){
			$applied_entries = $this->payment_service->fetchAppliedPaymentInvoices((int) $data['company_id'], (int) $id);
		}

		$response['payment'] = $this->payment_service->fetchPaymentForEdit((int) $data['company_id'], (int) $id);
		$response['applied_entries'] = $applied_entries;

		$response['full_access'] = 0;
		if((int) $response['payment']['status'] === PaymentStatus::NOT_APPLIED->value){
			$response['full_access'] = 1;
		}

		return $response;

	}

	public function update(PaymentCreateRequest $request, int $id){

		$data = $request->validated();

		$company_id = (int) $data['company_id'];
		$client_id = (int) $data['client_id'];
		$amount = (string) $data['amount'];
		$payment_type_id = (string) $data['payment_type'];
		$payment_id = (int) Sanitize::input($id);

		try{

			$this->payment_service->update($company_id, $client_id, $amount, $payment_id, $payment_type_id);

			return response(['message' => 'Payment updated successfully', 'validity' => 'payment_updated'], 200);

		}catch(PaymentException $e){
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
			$company_id = (int) Sanitize::input($request->input('company_id'));

			$this->payment_service->deletePayments($company_id, $ids);
			return response(['message' => 'Payment(s) deleted successfully', 'validity' => 'delete_success'], 200);

		}catch(PaymentException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

}
