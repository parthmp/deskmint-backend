<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoicesCustomField;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Invoice\InvoiceService;
use App\Traits\CustomFieldsPrinting;
use App\Traits\CustomFieldsUpsert;
use App\Traits\CustomFieldsValidation;
use App\Traits\PaymentGatewayDetails;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller{

	use CustomFieldsPrinting, PaymentGatewayDetails, CustomFieldsValidation, CustomFieldsUpsert;

	public function __construct(
		private ClientRepository $client_repository,
		private InvoiceRepository $invoice_repository,
		private ProductRepository $product_repository,
		private InvoiceService $invoice_service
	){

	}
    
	/**
	 * searchClients function
	 *
	 * @param Request $request
	 * @return Collection
	 */
	public function searchClients(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$searched = (string) Sanitize::input($request->input('searched'));

		try{
			return $this->client_repository->searchByName($company_id, $searched);
		}catch(Exception $e){
			return General::wentWrong();
		}
	}

	/**
	 * fetchInitialData function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchInitialData(Request $request){

		$v = Validator::make($request->all(), [
			'timezone_offset_minutes'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validator' => 'invalid_timezone'], config('global.error_code'));
		}

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$timezone_offset_minutes = (int) Sanitize::input($request->input('timezone_offset_minutes'));

		try{
			return $this->invoice_repository->getInitialData($request, $company_id, $timezone_offset_minutes);
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
