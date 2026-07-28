<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\TransactionStoreRequest;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Modules\DataTable\Requests\DataTableRequest;
use App\Services\Transaction\TransactionService;
use Exception;
use Illuminate\Http\Request;

class TransactionsController extends Controller {

	private array $additional_fields = [
		[
			'label'			=>	'c_code',
			'text'			=>	'Currency'
		],
		[
			'label'			=>	'invoice_number',
			'text'			=>	'invoice#'
		],
		[
			'label'			=>	'full_name',
			'text'			=>	'Full name'
		],
		[
			'label'			=>	'token_id_identifier',
			'text'			=>	'Token'
		],
		[
			'label'			=>	'gateway_fees_amount',
			'text'			=>	'Gateway fees'
		],
		[
			'label'			=>	'is_approved',
			'text'			=>	'Approved'
		],
		[
			'label'			=>	'is_payment_captured',
			'text'			=>	'Captured'
		],
		[
			'label'			=>	'is_echeck',
			'text'			=>	'Echeck'
		],
		[
			'label'			=>	'u_user',
			'text'			=>	'Voided by'
		],
		
	];

	private array $date_fields = [
		'created_at',
		'paid_at'
	];

	public function __construct(
		private TransactionService $transaction_service,
		private ArrangedDataTableColumns $arranged_data_table_columns
	){}

	public function index(DataTableRequest $request){

		$data = $request->validated();
		
		return $this->transaction_service->fetch($request);

	}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'transactions', 'transactions', null, 'transaction', remove_columns:['company_id', 'timezone_offset_minutes', 'currency_id', 'token_id_identifier', 'invoice_id', 'gateway_fees_amount', 'is_approved', 'is_payment_captured', 'is_echeck', 'voided_by'], additional_fields: $this->additional_fields);
	}
	

	public function saveArrangedColumns(Request $request){

		try{
			$this->arranged_data_table_columns->saveArrangedColumnsData($request, null, 'transactions', 'transactions', 'transaction', $this->additional_fields, $this->date_fields);
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(InvalidDataProvidedException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function show(GenericRequest $request, int $transaction_id){
		
		$transaction_id = (int) Sanitize::input($transaction_id);
		$data = $request->validated();

		try{
			
			$transaction = $this->transaction_service->fetchTransactionView($transaction_id, (int) $data['company_id']);
			
			if(empty($transaction)){
				return response(['message' => 'Invalid transaction provided', 'validity' => 'invalid_transaction'], config('global.error_code'));
			}

			return $transaction;

		}catch(Exception $e){
			return General::wentWrong();
		}
		

	}

}
