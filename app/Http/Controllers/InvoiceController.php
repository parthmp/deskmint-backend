<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\Invoice\FetchInvoiceRequest;
use App\Http\Requests\Product\SearchProductRequest;
use App\Jobs\GenerateInvoiceJob;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoicesCustomField;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Modules\CustomFields\CustomFields;
use App\Services\Invoice\Exceptions\InvoiceException;
use App\Services\Invoice\InvoiceService;
use Exception;
use Illuminate\Http\Request;

/**
 * InvoiceController class
 */
class InvoiceController extends Controller{

	private array $additional_fields = [
		[
			'label'			=>	'first_name',
			'text'			=>	'First name'
		],
		[
			'label'			=>	'last_name',
			'text'			=>	'Last name'
		],
		[
			'label'			=>	'client_company',
			'text'			=>	'Client company'
		],
		[
			'label'			=>	'c_code',
			'text'			=>	'Currency'
		]
	];

	private array $date_fields = [
		'due_date',
		'invoice_date',
		'created_at'
	];

	public function __construct(
		private InvoiceService $invoice_service,
		private ArrangedDataTableColumns $arranged_data_table_columns
	){}
    

	public function searchClients(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$searched = (string) Sanitize::input($request->input('searched'));

		try{
			return $this->invoice_service->searchClientByName($company_id, $searched);
		}catch(Exception $e){
			return General::wentWrong();
		}
	}


	public function fetchInitialData(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
	
		try{
			return $this->invoice_service->fetchInitialData($request, $company_id);
		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function fetchProducts(SearchProductRequest $request){
		
		$data = $request->validated();

		try{
			return $this->invoice_service->searchProductsByName($data['company_id'], $data['searched']);
		}catch(Exception $e){
			return General::wentWrong();
		}

		
	}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'invoices', 'invoices', InvoicesCustomField::class, 'invoice', remove_columns:['invoice_terms', 'send_email', 'pattern_matched', 'scan_chars', 'settings_snapshot', 'client_id', 'company_id'], additional_fields: $this->additional_fields);
	}
	

	public function saveArrangedColumns(Request $request){

		try{
			$this->arranged_data_table_columns->saveArrangedColumnsData($request, InvoicesCustomField::class, 'invoices', 'invoices', 'invoice', $this->additional_fields, $this->date_fields);
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(InvalidDataProvidedException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function index(Request $request){

		try{
			return $this->invoice_service->fetchIndex($request);
		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
		

	}

	/**
	 * sendInvoiceEmail function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return string
	 */
	private function sendInvoiceEmail(Request $request, int $company_id, int $invoice_id) : string {
		//send invoice in email if the setting is switched on.
		//should check for e invoice setting before sending xml in invoice servie class.
		$do_send = (bool) Sanitize::input($request->input('settings.send_invoice_in_email'));
		if($do_send){
			$timezone_offset_minutes = Sanitize::input($request->input('timezone_offset_minutes'));
			GenerateInvoiceJob::dispatch($company_id, $invoice_id, $timezone_offset_minutes, $this->invoice_service);
			return ' and invoice has been sent';
		}

		return '';
	}
	
	/**
	 * store function
	 *
	 * @param Request $request
	 * @return void
	 */
	public function store(Request $request){
		
		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{

			$this->invoice_service->validateAllForInvoice($request, $company_id);

			try{

				$invoice_id = $this->invoice_service->save($request, $company_id);

				$message = 'Invoice created successfully';
				$message .= $this->sendInvoiceEmail($request, $company_id, $invoice_id);

				return response(['message' => $message, 'validator' => 'invalid_created'], 200);

			}catch(Exception $e){
				return General::wentWrong();
			}
			
		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity(), 'tab_switch' => $e->getTab()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function update(Request $request, int $invoice_id){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$invoice_id = (int) Sanitize::input((string) $invoice_id);

		if(!$this->invoice_service->ifInvoiceExists($invoice_id)){
			return response(['message' => 'invalid data', 'validity' => 'invalid_data', 'tab_switch' => 0], config('global.error_code'));
		}

		$this->invoice_service->validateAllForInvoice($request, $company_id);

		$invoice_id = $this->invoice_service->save($request, $company_id, $invoice_id);

		$message = 'Invoice updated successfully';
		$message .= $this->sendInvoiceEmail($request, $company_id, $invoice_id);

	}

	public function show(FetchInvoiceRequest $request, int $invoice_id){

		$data = $request->validated();

		try{

			$invoice_id = (int) Sanitize::input($invoice_id);
			return $this->invoice_service->fetchInvoice($data['company_id'], $invoice_id, $data['timezone_offset_minutes']);

		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

		

	}

}
