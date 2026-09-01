<?php

namespace App\Services\Invoice;

use App\Jobs\GenerateInvoiceSnapshotJob;
use App\Models\Invoice;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\InvoiceGeneration\InvoiceEmailContent;
use App\Modules\InvoiceGeneration\InvoiceGenerator;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Credit\CreditRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Invoice\Exceptions\InvoiceException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceService{

	public function __construct(
		private InvoiceFetchService $invoice_fetch_service,
		private ClientRepository $client_repository,
		private ProductRepository $product_repository,
		private InvoiceValidationService $invoice_validation_service,
		private InvoiceSaveService  $invoice_save_service,
		private InvoiceRepository $invoice_repository,
		private InvoiceDBOperations $invoice_db_operations,
		private CreditRepository $credit_repository
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
	 * @param integer $company_id
	 * @return boolean
	 */
	public function ifInvoiceExists(int $invoice_id, int $company_id) : bool {
		$invoice = $this->invoice_repository->fetchInvoiceObjById($invoice_id, $company_id);
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
			throw new InvoiceException('One or more invoices has payment attached, unable to delete.', 'unable_to_delete_payment_attached', config('global.error_code'));
		}

		if($this->invoice_repository->ifInvoiceLockedMultipleCancelled($ids)){
			throw new InvoiceException('One or more invoices has been cancelled, unable to delete.', 'unable_to_delete_cancelled', config('global.error_code'));
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
	 * @param integer $company_id
	 * @return invoice|null
	 */
	public function fetchInvoiceById(int $invoice_id, int $company_id) : ?Invoice {
		return $this->invoice_repository->fetchInvoiceObjById($invoice_id, $company_id);
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

	/**
	 * markInvoiceSent function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return boolean
	 */
	public function markInvoiceSent(int $company_id, int $invoice_id) : bool {
		return $this->invoice_repository->markInvoiceSent($company_id, $invoice_id);
	}

	/**
	 * addCreditForInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param string $amount
	 * @return boolean
	 */
	public function addCreditForInvoice(int $company_id, int $invoice_id, string $amount, $uuid) : bool {

		$credit = $this->invoice_repository->addCredit($company_id, $invoice_id, $amount, $uuid);
		$credit = $this->invoice_repository->overwriteCreditForAmount($credit, $amount);
		return $this->invoice_repository->addLedgerEntry($company_id, $invoice_id, $credit->id, $amount, 'credit');

	}

	/**
	 * addPaymentForInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param string $amount
	 * @param integer $payment_type
	 * @return boolean
	 */
	public function addPaymentForInvoice(int $company_id, int $invoice_id, string $amount, int $payment_type) : bool {

		$payment = $this->invoice_repository->addPayment($company_id, $invoice_id, $amount, $payment_type, null);
		$payment = $this->invoice_repository->overwritePaymentForAmount($payment, $amount);
		return $this->invoice_repository->addLedgerEntry($company_id, $invoice_id, $payment->id, $amount, 'payment');

	}

	/**
	 * addCreditOrPaymentForInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param string $amount
	 * @param integer $payment_type
	 * @param string $type
	 * @return array
	 */
	public function addCreditOrPaymentForInvoice(int $company_id, int $invoice_id, string $amount, int $payment_type, string $uuid, string $type = 'credit') : array {
		
		return DB::transaction(function() use ($company_id, $invoice_id, $amount, $payment_type, $type, $uuid) {
			if($type === 'credit'){
				$this->addCreditForInvoice($company_id, $invoice_id, $amount, $uuid);
			}else if($type === 'payment'){
				$this->addPaymentForInvoice($company_id, $invoice_id, $amount, $payment_type);
			}else{
				throw new InvoiceException('Invalid type provided', 'invalid_type', (int) config('global.error_code'));
			}
			//update invoice here
			$data = $this->modifyInvoiceForAmount($company_id, $invoice_id);

			DB::afterCommit(function() use ($company_id, $invoice_id) {
				GenerateInvoiceSnapshotJob::dispatch($company_id, $invoice_id, true, false);
			});

			return $data;

		});

	}

	/**
	 * modifyInvoiceForAmount function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function modifyInvoiceForAmount(int $company_id, int $invoice_id) : array {
		
		$leger_entries = $this->invoice_repository->fetchLedgerEntriesOfInvoice($company_id, $invoice_id);

		$so_far_applied_to_invoice = BigDecimal::of(0);

		foreach($leger_entries as $entry){
			$so_far_applied_to_invoice = $so_far_applied_to_invoice->plus($entry['total_applied']);
		}

		$invoice = $this->invoice_repository->fetchFullInvoiceData($company_id, $invoice_id);

		$status = InvoiceStatus::DRAFT->value;
		if($invoice->sent_at !== null || (int) $invoice->reminders_sent > 0){
			$status = InvoiceStatus::SENT->value;
		}
		
		$total = BigDecimal::of($invoice->total);

		if($so_far_applied_to_invoice->isEqualTo($total)){
			$status = InvoiceStatus::PAID->value;
		}else if($so_far_applied_to_invoice->isLessThan($total) && $so_far_applied_to_invoice->isGreaterThan(BigDecimal::of(0))){
			$status = InvoiceStatus::PARTIALLY_PAID->value;
		}

		
		$balance_due = $total->minus($so_far_applied_to_invoice);
		$balance_due = $balance_due->toScale(2, RoundingMode::HalfUp)->__toString();

		$this->invoice_repository->updateInvoiceStatusAndAmount($invoice, $status, $balance_due);

		$highlight = 'info';

		if($status === InvoiceStatus::SENT->value || $status === InvoiceStatus::PARTIALLY_PAID->value || $status === InvoiceStatus::PAID->value){
			$highlight = 'success';
		}

		return [
			'highlight'		=>	$highlight,
			'status'		=>	$status,
			'balance_due'	=>	$balance_due,
			'status_text'	=>	InvoiceStatus::getInvoiceStatusLabel($status)
		];

	}

	/**
	 * validatePaymentType function
	 *
	 * @param integer $payment_type
	 * @return void
	 */
	public function validatePaymentType(int $payment_type) : void {
		if(!$this->invoice_repository->ifPaymentTypeExists($payment_type)){
			throw new InvoiceException('Invalid payment type provided', 'invalid_payment_type', (int) config('global.error_code'));
		}
	}

	public function fetchInvoiceForApplyUnapplyCredit(int $company_id, int $invoice_id) : array {

		$invoice = $this->invoice_repository->fetchByIdWithComapanyId($company_id, $invoice_id);
		
		return [
			'id'				=>	$invoice->id,
			'amount'			=>	$invoice->total,
			'amount_left'		=>	$invoice->balance_due,
			'due'				=>	$invoice->balance_due,
			'invoice_number'	=>	$invoice->invoice_number,
			'currency_code'		=>	$invoice->currency_code,
			'full_name'			=>	$invoice->first_name.' '.$invoice->last_name,
		];

	}

	/**
	 * ifCreditNumberExists function
	 *
	 * @param string $credit_number
	 * @param integer|null $ignore_id
	 * @return boolean
	 */
	public function ifCreditNumberExists(string $credit_number, int $ignore_id = null) : bool {

		if($this->credit_repository->ifCreditNumberExists($credit_number, $ignore_id)){
			throw new InvoiceException('Credit number already exists', 'already_exists_cn', (int) config('global.error_code'));
		}

		return false;

	}

}