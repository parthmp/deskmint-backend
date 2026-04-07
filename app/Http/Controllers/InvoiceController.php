<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\Product\SearchProductRequest;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoicesCustomField;
use App\Modules\CustomFields\CustomFields;
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

	public function fetchProducts(SearchProductRequest $request){
		
		$data = $request->validated();

		try{
			return $this->invoice_service->searchProductsByName($data['company_id'], $data['searched']);
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

		try{

			$this->invoice_service->validateAllForInvoice($request, $company_id);

			try{

				$this->invoice_service->save($request, $company_id);
				return response(['message' => 'Invoice created successfully', 'validator' => 'invalid_created'], 200);

			}catch(Exception $e){
				return General::wentWrong();
			}
			
		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
