<?php

namespace App\Services\Invoice;

use App\Helpers\Sanitize;
use Illuminate\Http\Request;

class InvoiceBaseService{

	public function __construct(private InvoiceValidationService $invoice_validation_service){}

	public function saveOrUpdate(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$errors = $this->invoice_validation_service->validateAllForInvoice($request, $company_id);
		
		if($errors !== null){
			return $errors;
		}

		// try{

		// 	$invoice_id = $this->invoice_repository->upsertInvoice($request);

		// 	/* custom fields insertion */
		// 	$this->upsertCustomFieldValues($request, $invoice_id, InvoicesCustomField::class, InvoiceCustomFieldValue::class, 'invoices_flat', 'invoice', true);

		// 	$this->invoice_service->insertProductRows($request, $invoice_id, $company_id);

		// 	/* override manual reset here */
		// 	$this->invoice_service->resetManualInvoieNumberResetFlag($company_id);

		// 	return response(['message' => 'Invoice created successfully', 'validator' => 'invalid_created'], 200);

		// }catch(Exception $e){

		// 	return General::wentWrong();

		// }

	}

}