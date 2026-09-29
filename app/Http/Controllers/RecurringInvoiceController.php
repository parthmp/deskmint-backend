<?php

namespace App\Http\Controllers;

use App\Exceptions\RecurringInvoiceException;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Services\RecurringInvoice\RecurringInvoiceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

		//try{

			$this->recurring_invoice_service->validate($request, $company_id);
			$send_email = (bool) Sanitize::input($request->input('settings.send_email'));

			DB::transaction(function () use ($request, $company_id, $send_email) {
				
				$recurring_invoice = $this->recurring_invoice_service->save($request, $company_id);
				
				DB::afterCommit(function() use ($send_email, $recurring_invoice, $company_id){
				
					$this->recurring_invoice_service->executeActions(company_id: $company_id, ids: [$recurring_invoice->id], send_email: $send_email, start_subscription: false, mark_paid: false);
					
				});

			});

			

		// }catch(RecurringInvoiceException $e){
		// 	return response(['message' => $e->getMessage(), 'validity' => $e->getValidity(), 'tab_switch' => $e->getTab()], $e->getCode());
		// }catch(Exception $e){
		// 	return General::wentWrong();
		// }

	}

}
