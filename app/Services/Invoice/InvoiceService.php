<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\InvoiceGeneration\InvoiceEmailContent;
use App\Modules\InvoiceGeneration\InvoiceGenerator;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Invoice\Exceptions\InvoiceException;
use Exception;
use Illuminate\Http\Request;

class InvoiceService{

	public function __construct(
		private InvoiceFetchService $invoice_fetch_service,
		private ClientRepository $client_repository,
		private ProductRepository $product_repository,
		private InvoiceValidationService $invoice_validation_service,
		private InvoiceSaveService  $invoice_save_service,
		private InvoiceRepository $invoice_repository,
		private InvoiceDBOperations $invoice_db_operations
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
	public function validateAllForInvoice(Request $request, int $company_id, int $invoice_id = 0){
		return $this->invoice_validation_service->validateAllForInvoice($request, $company_id, $invoice_id);
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
	 * @param integer $invoice_id
	 * @return integer
	 */
	public function save(Request $request, int $company_id, int $invoice_id = 0) : int {
		return $this->invoice_save_service->save($request, $company_id, $invoice_id);
	}

	/**
	 * generateInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return void
	 */
	public function generateInvoice(int $company_id, int $invoice_id) : void {
		
		$invoice_generator = app(InvoiceGenerator::class)->setCompanyId((int) $company_id)->setInvoiceId((int) $invoice_id)->exec();
		$invoice_generator = $invoice_generator->generatePDF(save:true, add_random:false);

		$xml_on = $this->invoice_repository->ifEInvoiceIsOn($invoice_id);

		if($xml_on){
			$invoice_generator = $invoice_generator->generateEInvoice();
		}
		
		$filename = $invoice_generator->getFilename();
		$this->invoice_repository->updateInvoiceFiles((int) $invoice_id, $filename.'.pdf', ($xml_on) ? $filename.'.xml' : '');

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

	/**
	 * ifInvoiceExists function
	 *
	 * @param integer $invoice_id
	 * @return boolean
	 */
	public function ifInvoiceExists(int $invoice_id) : bool {
		
		$invoice = $this->invoice_repository->fetchInvoiceObjById($invoice_id);
		
		return ($invoice) ? true : false;

	}

	/**
	 * deleteInvoices function
	 *
	 * @param array $ids
	 * @return boolean
	 */
	public function deleteInvoices(array $ids) : bool {

		if(empty($ids)){
			throw new InvoiceException('No valid IDs provided', 'invalid_ids', config('global.error_code'));
		}

		foreach($ids as $id){
			if(!is_numeric($id)){
				throw new InvoiceException('All IDs must be numeric', 'non_numeric', config('global.error_code'));
			}
		}

		if($this->invoice_repository->ifInvoiceLockedMultiple($ids)){
			throw new InvoiceException('One or more invoices has payment attached, unable to delete.', 'unable to delete', config('global.error_code'));
		}

		if($this->invoice_repository->ifInvoiceLockedMultipleCancelled($ids)){
			throw new InvoiceException('One or more invoices has been cancelled, unable to delete.', 'unable to delete cancelled', config('global.error_code'));
		}

		try{

			$flat_table = 'invoices_flat';

			$this->invoice_repository->deleteRecordsByInvoiceIds($flat_table, $ids);
			
			return true;

		}catch(Exception $e){
			throw new InvoiceException('Something went wrong', 'something_wrong', 500);
		}
		
	}

	/**
	 * fetchSnapshot function
	 *
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchSnapshot(int $invoice_id) : array {
		return $this->invoice_fetch_service->fetchSnapshot($invoice_id);
	}

	/**
	 * getDataForSendingInvoice function
	 *
	 * @param integer $invoice_id
	 * @return array
	 */
	public function getDataForSendingInvoice(int $invoice_id, bool $reminder_content = false) : array {

		$invoice = $this->invoice_repository->fetchInvoiceWithClientAndCurrency($invoice_id);
		$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($invoice->company_id)->setInvoiceId($invoice->id)->execRequiredSettings();
		$content = $this->invoice_db_operations->fetchEmailContentSettings();
		
		if(isset($content['settings_json'])){
			$email_json = json_decode($content['settings_json'], true);
		}else{
			$email_json = $content;
		}
		
		if($reminder_content){ //for reminder email
			return ['invoice' => $invoice, 'content' => $email_json['email_content_reminder']];
		}

		return ['invoice' => $invoice, 'content' => $email_json['email_content_invoice']];

	}

	/**
	 * prepareEmailData function
	 *
	 * @param integer $invoice_id
	 * @param boolean $reminder_content
	 * @return array
	 */
	public function prepareEmailData(int $invoice_id, bool $reminder_content = false) : array {
		
		$data = $this->getDataForSendingInvoice($invoice_id, $reminder_content);

		$content_class = new InvoiceEmailContent();
		return $content_class->setDisk(INVOICES_DISK)->setInvoice($data['invoice'])->setInvoiceContent($data['content'])->getContent();

	}

	/**
	 * fetchInvoiceById function
	 *
	 * @param integer $invoice_id
	 * @return invoice|null
	 */
	public function fetchInvoiceById(int $invoice_id) : ?Invoice {
		return $this->invoice_repository->fetchInvoiceObjById($invoice_id);
	}

	/**
	 * updateInvoiceStatus function
	 *
	 * @param integer $invoice_id
	 * @param integer $status
	 * @return boolean
	 */
	public function updateInvoiceStatus(int $invoice_id, int $status) : bool {
		return $this->invoice_repository->updateInvoiceStatus($invoice_id, $status);
	}

}