<?php

namespace App\Http\Controllers;

use App\Exceptions\RecurringInvoiceException;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\RecurringInvoicesCustomField;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Modules\DataTable\Requests\DataTableRequest;
use App\Services\RecurringInvoice\RecurringInvoiceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecurringInvoiceController extends Controller {

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
			'label'			=>	'payment_gateway',
			'text'			=>	'Gateway'
		],
		[
			'label'			=>	'payment_type',
			'text'			=>	'Payment type'
		],
		[
			'label'			=>	'mark_paid_automatically',
			'text'			=>	'Mark paid'
		],
		[
			'label'			=>	'last_invoice_created_at',
			'text'			=>	'Last invoice at'
		],
		[
			'label'			=>	'next_invoice_on',
			'text'			=>	'Next invoice after'
		],
		
	];

	private array $date_fields = [
		'last_invoice_created_at',
		'next_invoice_on',
		'created_at'
	];


	public function __construct(
		private RecurringInvoiceService $recurring_invoice_service,
		private ArrangedDataTableColumns $arranged_data_table_columns,
	){}


	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'recurring_invoices', 'recurring_invoices', RecurringInvoicesCustomField::class, 'recurring_invoice', remove_columns:['company_id', 'client_id', 'currency_id', 'invoice_terms', 'payment_gateway', 'payment_type_id', 'mark_paid_automatically', 'timezone', 'send_invoice_email', 'last_invoice_created_at', 'next_invoice_on', 'settings_snapshot', 'hidden_sent_at', 'updated_at'], additional_fields: $this->additional_fields);
	}
	

	public function saveArrangedColumns(Request $request){

		try{
			$this->arranged_data_table_columns->saveArrangedColumnsData($request, RecurringInvoicesCustomField::class, 'recurring_invoices', 'recurring_invoices', 'recurring_invoice', $this->additional_fields, $this->date_fields);
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(InvalidDataProvidedException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong($e);
		}

	}

	
    public function fetchInitialData(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
	
		try{
			return $this->recurring_invoice_service->fetchInitialData($request, $company_id);
		}catch(RecurringInvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong($e);
		}

	}


	public function store(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{

			$this->recurring_invoice_service->validate($request, $company_id);
			$send_email = (bool) Sanitize::input($request->input('settings.send_email'));

			DB::transaction(function () use ($request, $company_id, $send_email) {
				
				$recurring_invoice = $this->recurring_invoice_service->save($request, $company_id);
				
				DB::afterCommit(function() use ($send_email, $recurring_invoice, $company_id){
				
					$this->recurring_invoice_service->executeActions(company_id: $company_id, ids: [$recurring_invoice->id], send_email: $send_email, start_subscription: false);
					
				});

			});

			

		}catch(RecurringInvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity(), 'tab_switch' => $e->getTab()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function index(DataTableRequest $request){

		$data = $request->validated();
		try{
			return $this->recurring_invoice_service->fetchIndex($request);
		}catch(Exception $e){
			return General::wentWrong($e);
		}
		
	}

}
