<?php

namespace App\Services\Invoice;

use App\Enums\Credits\CreditStatus;
use App\Helpers\Sanitize;
use App\Jobs\GenerateInvoiceSnapshotJob;
use App\Models\Credit;
use App\Models\Invoice;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\InvoiceGeneration\InvoiceEmailContent;
use App\Modules\InvoiceGeneration\InvoiceGenerator;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Credit\CreditRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Payment\PaymentRepository;
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
		private CreditRepository $credit_repository,
		private PaymentRepository $payment_repository,
		private CreditApplyValidationService $credit_apply_validation_service
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
	 * @param array $selects
	 * @return Invoice|null
	 */
	public function fetchInvoiceById(int $invoice_id, int $company_id, array $selects = ['*']) : ?Invoice {
		return $this->invoice_repository->fetchInvoiceObjById($invoice_id, $company_id, $selects);
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
	 * @param string $uuid
	 * @return boolean
	 */
	public function addPaymentForInvoice(int $company_id, int $invoice_id, string $amount, int $payment_type, string $uuid) : bool {

		$payment = $this->invoice_repository->addPayment($company_id, $invoice_id, $amount, $payment_type, $uuid, null);
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
				$this->addPaymentForInvoice($company_id, $invoice_id, $amount, $payment_type, $uuid);
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

	//starts apply unapply credit features.

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
	 * @param integer $company_id
	 * @param string $credit_number
	 * @param integer|null $ignore_id
	 * @return boolean
	 */
	public function ifCreditNumberExists(int $company_id, string $credit_number, ?int $ignore_id = null) : bool {

		if($this->credit_repository->ifCreditNumberExists($company_id, $credit_number, $ignore_id)){
			throw new InvoiceException('Credit number already exists', 'already_exists_cn', (int) config('global.error_code'));
		}

		return false;

	}

	/**
	 * ifPaymentNumberExists function
	 *
	 * @param integer $company_id
	 * @param string $payment_number
	 * @param integer|null $ignore_id
	 * @return boolean
	 */
	public function ifPaymentNumberExists(int $company_id, string $payment_number, ?int $ignore_id = null) : bool {

		if($this->payment_repository->ifPaymentNumberExists($company_id, $payment_number, $ignore_id)){
			throw new InvoiceException('Payment number already exists', 'already_exists_pn', (int) config('global.error_code'));
		}

		return false;

	}

	/**
	 * fetchCreditsForApplyUnapply function
	 *
	 * @param integer $company_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param integer $invoice_id
	 * @param string $searched
	 * @param array $locally_applied_ids
	 * @param array $fully_applied_ids
	 * @return array
	 */
	public function fetchCreditsForApplyUnapply(int $company_id, int $currency_id, int $client_id, int $invoice_id, string $searched, array $locally_applied_ids, array $fully_applied_ids = []) : array {
		return $this->invoice_repository->fetchCreditsForApplyUnapply($company_id, $currency_id, $client_id, $invoice_id, $searched, $locally_applied_ids, $fully_applied_ids);
	}

	/**
	 * fetchAlreadyAppliedCredits function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @return array
	 */
	public function fetchAlreadyAppliedCredits(int $company_id, int $invoice_id, int $currency_id, int $client_id) : array {
		return $this->invoice_repository->fetchAlreadyAppliedCredits($company_id, $invoice_id, $currency_id, $client_id);
	}

	/**
	 * modifyLedger function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param array $applied_credits
	 * @param array $removed_credit_ids
	 * @return void
	 */
	public function modifyLedger(int $company_id, int $invoice_id, array $applied_credits, array $removed_credit_ids) : void {

		//delete first
		$this->invoice_repository->removeLedgerEntries($company_id, $invoice_id, $removed_credit_ids);
		
		//now check which ones were modified. this is required because later on, we will use updated_at to track which credit or payment applied when.
		$update = [];
		$insert = [];

		$entries = $this->invoice_repository->fetchLedgerEntriesForApply($company_id, $invoice_id, 'credit');

		foreach($applied_credits as $credit){

			$credit['id'] = Sanitize::input($credit['id']);
			$credit['amount'] = Sanitize::input($credit['amount']);

			$found = false;
			
			foreach($entries as $entry){

				if((int) $credit['id'] === (int) $entry['credit_id']){

					$found = true;

					$already_applied = BigDecimal::of($entry['applied_amount_from_credits']);
					$to_be_applied = BigDecimal::of($credit['amount']);

					//update only if amount changes.
					if(!$already_applied->isEqualTo($to_be_applied)){
						$update[] = [
							'id'							=>	$entry['id'],
							'applied_amount_from_credits'	=>	$credit['amount'],
							'applied_amount_from_payments'	=>	0,
							'total_applied'					=>	$credit['amount']
						];
					}

				}

			}

			if(!$found){
				//this is where we add new entries.
				$insert[] = [
					'company_id'						=>	$company_id,
					'invoice_id'						=>	$invoice_id,
					'payment_id'						=>	null,
					'credit_id'							=>	$credit['id'],
					'applied_amount_from_payments'		=>	0,
					'applied_amount_from_credits'		=>	$credit['amount'],
					'total_applied'						=>	$credit['amount'],
					'created_at'						=>	now(),
					'updated_at'						=>	now()
				];
			}

		}

		//now need chunks.
		if((int) count($insert) > 0){
			$insert = array_chunk($insert, 50);
			$this->invoice_repository->insertNewLedgerEntries($insert);
		}
		
		if((int) count($update) > 0){
			$update = array_chunk($update, 50);
			$this->invoice_repository->updateLedgerEntries($update);
		}
		

	}

	/**
	 * updateEntries function
	 *
	 * @param integer $company_id
	 * @param array $entry_ids
	 * @return void
	 */
	public function updateEntries(int $company_id, array $entry_ids) : void {
		
		$ledger_entries = $this->invoice_repository->fetchMultipleLedgerOfIds($company_id, $entry_ids);
		$credits = $this->invoice_repository->fetchMultipleEntriesByIdsForApply($company_id, Credit::class, $entry_ids);

		$update = [];
		foreach($credits as $credit){
			
			$sum = BigDecimal::of(0);
			$applied_amount_from_db = BigDecimal::of($credit['applied_amount']);
			$status = CreditStatus::NOT_APPLIED->value;
			$total = BigDecimal::of($credit['amount']);

			foreach($ledger_entries as $entry){
				if((int) $entry['credit_id'] === (int) $credit['id']){
					$sum = $sum->plus($entry['applied_amount_from_credits']);
				}
			}

			if(!$sum->isEqualTo($applied_amount_from_db)){

				if($sum->isLessThan($total) && $sum->isGreaterThan(BigDecimal::of(0))){
					$status = CreditStatus::PARTIALLY_APPLIED->value;
				}else if($sum->isEqualTo($total)){
					$status = CreditStatus::APPLIED->value;
				}

				$left = $total->minus($sum);

				if($left->isLessThan(BigDecimal::of(0))){
					throw new InvoiceException('Something went wrong in calculation', 'unexpected_error_calc', (int) config('global.error_code'));
				}

				$update[] = [
					'id' 						=> $credit['id'],
					'status' 					=> $status,
					'applied_amount' 			=> $sum->toScale(2, RoundingMode::HalfUp)->__toString(),
					'amount_left_to_be_applied' => $left->toScale(2, RoundingMode::HalfUp)->__toString(),
				];
			}

		}

		if((int) count($update) > 0){
			$update = array_chunk($update, 50);
			$this->invoice_repository->updateMultipleEntries($update);
		}

	}

	/**
	 * updateInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return void
	 */
	public function updateInvoice(int $company_id, int $invoice_id) : void {

		$entries = $this->invoice_repository->fetchLedgerForApplying($company_id, $invoice_id);
		$invoice = $this->invoice_repository->fetchInvoiceObjById($invoice_id, $company_id, ['sent_at', 'reminders_sent', 'total', 'balance_due']);

		$status = InvoiceStatus::DRAFT->value;

		if($invoice['sent_at'] !== null || (int) $invoice['reminders_sent'] > 0){
			$status = InvoiceStatus::SENT->value;
		}

		$sum = BigDecimal::of(0);
		foreach($entries as $entry){
			$sum = $sum->plus($entry['total_applied']);
		}

		$total = BigDecimal::of($invoice['total']);

		$new_balance_due = $total->minus($sum);
		$old_balance_due = BigDecimal::of($invoice['balance_due']);

		if($new_balance_due->isEqualTo($old_balance_due)){
			return ;
		}

		if($sum->isLessThan($total) && !$sum->isEqualTo(BigDecimal::of(0))){
			$status = InvoiceStatus::PARTIALLY_PAID->value;
		}else if($sum->isEqualTo($total)){
			$status = InvoiceStatus::PAID->value;
		}

		if($sum->isGreaterThan($total)){
			throw new InvoiceException('Something went wrong in calculation', 'unexpected_error_calc', (int) config('global.error_code'));
		}

		$update = [
			'status'		=>	$status,
			'balance_due'	=>  $new_balance_due->toScale(2, RoundingMode::HalfUp)->__toString(),	
		];

		$this->invoice_repository->updateInvoiceForApply($company_id, $invoice_id, $update);

	}

	public function applyUnapplyCredits(int $company_id, int $invoice_id, array $applied, array $removed_ids) : void {

		DB::transaction(function() use ($company_id, $invoice_id, $applied, $removed_ids){

			//modify ledger first.
			$this->modifyLedger($company_id, $invoice_id, $applied, $removed_ids);
			//now update credits
			$applied_ids = array_values(array_unique($this->credit_apply_validation_service->getIds($applied)));
			$removed_ids = array_values(array_unique($removed_ids));
			$merged_ids = array_merge($applied_ids, $removed_ids);
			$this->updateEntries($company_id, $merged_ids);
			$this->updateInvoice($company_id, $invoice_id);

			DB::afterCommit(function() use ($company_id, $invoice_id){
				GenerateInvoiceSnapshotJob::dispatch($company_id, $invoice_id, true, false);	
			});

		});
		
	}

}