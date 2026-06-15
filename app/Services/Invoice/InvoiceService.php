<?php

namespace App\Services\Invoice;

use App\Modules\InvoiceGeneration\InvoiceGenerator;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Product\ProductRepository;
use Illuminate\Http\Request;

class InvoiceService{

	public function __construct(
		private InvoiceFetchService $invoice_fetch_service,
		private ClientRepository $client_repository,
		private ProductRepository $product_repository,
		private InvoiceValidationService $invoice_validation_service,
		private InvoiceSaveService  $invoice_save_service,
		private InvoiceRepository $invoice_repository
	){}

	/**
	 * searchClientByName function
	 *
	 * @param integer $company_id
	 * @param string $search_term
	 * @return array
	 */
	public function searchClientByName(int $company_id, string $search_term) : array {
		return $this->client_repository->searchByName($company_id, $search_term);
	}

	/**
	 * validateInvoiceDetails function
	 *
	 * @param Request $request
	 * @return bool
	 */
	// public function validateInvoiceDetails(Request $request) : bool {
	// 	//return $this->invoice_validation_service->validateInvoiceDetails($request);
	// }

	/**
	 * validateInvoiceSettings function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	// public function validateInvoiceSettings(Request $request) : bool {
	// 	//return $this->invoice_validation_service->validateInvoiceSettings($request);
	// }

	/**
	 * ifSubmittedFieldsAreSameAsDefined function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	// public function ifSubmittedFieldsAreSameAsDefined(Request $request, int $company_id) : bool {
	// 	//return $this->invoice_validation_service->ifSubmittedFieldsAreSameAsDefined($request, $company_id);
	// }

	/**
	 * validateAllForInvoice function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return mixed
	 */
	public function validateAllForInvoice(Request $request, int $company_id){
		return $this->invoice_validation_service->validateAllForInvoice($request, $company_id);
	}
	
	/**
	 * fetchInitialData function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchInitialData(Request $request, int $company_id) : array {
		return $this->invoice_fetch_service->fetchInitialData($request, $company_id);
	}

	/**
	 * searchProductsByName function
	 *
	 * @param integer $company_id
	 * @param string $search_term
	 * @return array
	 */
	public function searchProductsByName(int $company_id, string $search_term) : array {
		return $this->product_repository->searchByName($company_id, $search_term);
	}

	/**
	 * save function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return integer
	 */
	public function save(Request $request, int $company_id) : int {
		return $this->invoice_save_service->save($request, $company_id);
	}

	
	public function sendInvoice(int $company_id, int $invoice_id, string $time_offset_minutes) : void {
		
		$invoice_generator = new InvoiceGenerator((int) $company_id, (int) $invoice_id, (string) $time_offset_minutes);
		$invoice_generator = $invoice_generator->generatePDF(save:true, add_random:true);

		if($this->invoice_repository->ifEInvoiceIsOn($invoice_id)){
			$invoice_generator = $invoice_generator->generateEInvoice();
		}
		
		$invoice_generator->sendEmail();

	}

	/**
	 * fetchIndex function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchIndex(Request $request) : array {
		return $this->invoice_fetch_service->fetchIndex($request);
	}

	/**
	 * fetchInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param integer $timezone_offset_minutes
	 * @return array
	 */
	public function fetchInvoice(int $company_id, int $invoice_id, int $timezone_offset_minutes) : array {
		return $this->invoice_fetch_service->fetchInvoice($company_id, $invoice_id, $timezone_offset_minutes);
	}

}