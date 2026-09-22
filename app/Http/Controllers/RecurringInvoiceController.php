<?php

namespace App\Http\Controllers;

use App\Exceptions\RecurringInvoiceException;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Services\RecurringInvoice\RecurringInvoiceService;
use Exception;
use Illuminate\Http\Request;

class RecurringInvoiceController extends Controller {

	public function __construct(
		private RecurringInvoiceService $recurring_invoice_service
	){}

	
    public function fetchInitialData(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
	
		try{
			return $this->recurring_invoice_service->fetchInitialData($request, $company_id);
		}catch(RecurringInvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}


	public function store(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

	}

}
