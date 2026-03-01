<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoicesCustomField;
use App\Services\Invoice\Exceptions\InvoiceException;
use App\Services\Invoice\InvoiceService;
use Exception;
use Illuminate\Http\Request;

/**
 * InvoiceController class
 */
class InvoiceController extends Controller{

	public function __construct(
		private InvoiceService $invoice_service
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

	public function fetchProducts(Request $request){
		
		$company_id = (int) Sanitize::input($request->input('company_id'));
		$searched = (string) Sanitize::input($request->input('searched'));

		try{	
			return $this->product_repository->searchByName($company_id, $searched);
		}catch(Exception $e){
			return General::wentWrong();
		}

		
	}

	
	/**
	 * store function
	 *
	 * @param Request $request
	 * @return void
	 */
	public function store(Request $request){
		
		$company_id = (int) Sanitize::input($request->input('company_id'));

		$errors = $this->invoice_service->validateAllForInvoice($request, $company_id);
		
		if($errors !== null){
			return $errors;
		}

		try{

			$invoice_id = $this->invoice_repository->insertInvoice($request);

			/* custom fields insertion */
			$this->upsertCustomFieldValues($request, $invoice_id, InvoicesCustomField::class, InvoiceCustomFieldValue::class, 'invoices_flat', 'invoice', true);

			$this->invoice_service->insertProductRows($request, $invoice_id, $company_id);

			/* override manual reset here */
			$this->invoice_service->resetManualInvoieNumberResetFlag($company_id);

			return response(['message' => 'Invoice created successfully', 'validator' => 'invalid_created'], 200);

		}catch(Exception $e){

			return General::wentWrong();

		}

		



	}

}
