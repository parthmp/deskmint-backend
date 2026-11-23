<?php

namespace App\Services\Invoice;

use App\Enums\Credits\CreditStatus;
use App\Helpers\Sanitize;
use App\Jobs\GenerateInvoiceSnapshotJob;
use App\Models\Credit;
use App\Models\Invoice;
use App\Modules\ApplyUnapply\Common\Traits\ApplyUnapplyCommon;
use App\Modules\DataTable\Requests\DataTableRequest;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\InvoiceGeneration\InvoiceEmailContent;
use App\Modules\InvoiceGeneration\InvoiceGenerator;
use App\Modules\InvoiceGeneration\InvoiceSnapshot;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Credit\CreditRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Payment\PaymentRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Invoice\Exceptions\InvoiceException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceService{

	use ApplyUnapplyCommon;

	public function __construct(
		private InvoiceFetchService $invoice_fetch_service,
		private ClientRepository $client_repository,
		private ProductRepository $product_repository,
		private InvoiceValidationService $invoice_validation_service,
		private InvoiceSaveService  $invoice_save_service,
		private InvoiceRepository $invoice_repository,
		private InvoiceDBOperations $invoice_db_operations,
		private CreditRepository $credit_repository,
		private PaymentRepository $payment_repository
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
	 * @param string $segment
	 * @return array
	 */
	public function fetchIndex(DataTableRequest $request, string $segment) : array {
		return $this->invoice_fetch_service->fetchIndex($request, $segment);
	}

	/**
	 * fetchInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchInvoice(int $company_id, int $invoice_id) : array {
		return $this->invoice_fetch_service->fetchInvoice($company_id, $invoice_id);
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
		$content = $this->invoice_db_operations->fetchInvoiceEmailContentSettings();
		//$content = $this->invoice_db_operations->fetchEmailContentSettings();
		
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
	 * @param string $uuid
	 * @return boolean
	 */
	public function addCreditForInvoice(int $company_id, int $invoice_id, string $amount, string $uuid) : bool {

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
	 * validateArchiveRequest function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @return boolean
	 */
	private function validateArchiveRequest(int $company_id, array $ids) : bool {

		$counted = $this->invoice_repository->fetchInvoiceCountByIds($company_id, $ids);
		return (int) $counted === (int) count($ids);

	}

	/**
	 * changeArchiveStatus function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @param integer $archived_status
	 * @return void
	 */
	public function changeArchiveStatus(int $company_id, array $ids, int $archived_status) : void {
		
		$ids = array_values(array_unique($ids));

		if(!$this->validateArchiveRequest($company_id, $ids)){
			throw new InvoiceException('Invalid invoices provided', 'invalid_ids', (int) config('global.error_code'));
		}

		$this->invoice_repository->changeArchivedStatus($company_id, $ids, $archived_status);

	}

	/**
	 * fetchInvoiceLedger function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchInvoiceLedger(int $company_id, int $invoice_id) : array {
		return $this->invoice_fetch_service->fetchInvoiceLedger($company_id, $invoice_id);
	}

	/**
	 * generateSnapshot function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return void
	 */
	public function generateSnapshot(int $company_id, int $invoice_id) : void {

		$invoice = $this->invoice_repository->fetchInvoiceData($company_id, $invoice_id, ['timezone']);

		$snapshot = app(InvoiceSnapshot::class)
							->setCompanyId($company_id)
							->setInvoiceId($invoice_id)
							->setTimezone((string) $invoice->timezone)
							->setLogoSnapsot()
							->setGeneralSettings()
							->setClientSnapshot()
							->setCompanySnapshot()
							->setInvoiceSnapshot()
							->setInvoiceRowsSnapshot()
							->setTotalsSnapshot()
							->setTermsSnapshot()
							->output();

		$this->invoice_repository->updateInvoiceSnapshot($invoice_id, $snapshot);

	}

	/**
	 * getAppliedAmountOfInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return string
	 */
	public function getAppliedAmountOfInvoice(int $company_id, int $invoice_id) : string {

		$applied = BigDecimal::of(0);

		$rows = $this->invoice_repository->fetchAppliedAmountRowsForInvoice($company_id, $invoice_id, ['total_applied']);

		if($rows){
			foreach($rows as $row){
				$applied = $applied->plus(BigDecimal::of($row->total_applied));
			}
		}
		
		return $applied->toScale(2, RoundingMode::HalfUp)->__toString();

	}

	/**
	 * refundsFetchInit function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function refundsFetchInit(int $company_id, int $invoice_id) : array {

		$invoice = $this->invoice_repository->fetchById($invoice_id);

		if(!$invoice){
			throw new InvoiceException('Invalid invoice provided', 'invalid_invoice_id', (int) config('global.error_code'));
		}

		$applied_amount = $this->getAppliedAmountOfInvoice($company_id, $invoice_id);

		return [
			'invoice_number'		=>	$invoice->invoice_number,
			'client'				=>	$invoice->first_name.' '.$invoice->last_name,
			'currency'				=>	$invoice->currency_code,
			'applied_amount'		=>	$applied_amount,
			'total'					=>	$invoice->total,
		];

	}

	/**
	 * assembleDropDownData function
	 *
	 * @param array $data
	 * @return array
	 */
	private function assembleDropDownData(array $data) : array {

		$dropdown = [];

		foreach($data as $row){

			$applied = BigDecimal::of($row['total_applied']);
			$refunded = BigDecimal::of($row['total_applied_refund']);

			$sum = $applied->minus($refunded);

			if($sum->isGreaterThan(BigDecimal::of(0))){

				$payment_gateway = PaymentGateway::getLabelByValue(PaymentGateway::NONE->value);
				$type = 'Credit';

				if(array_key_exists('payment_gateway', $row)){
					$type = 'Payment';
				}

				if(isset($row['payment_gateway'])){
					if($row['payment_gateway']){
						$payment_gateway = PaymentGateway::getLabelByValue((int) $row['payment_gateway']);
					}
				}

				$amount = $sum->toScale(2, RoundingMode::HalfUp)->__toString();

				$text = $amount.' '.$row['code'].' '.$type.' ('.$payment_gateway.')';

				$dropdown[] = [
					
					'text'		=>	$text,
					'value'		=>	$row['id'],
					'meta'		=>	[
						'id'					=>	$row['id'],
						'amount'				=>	$amount,
						'filled_amount'			=>	'',
						'type'					=>	$type,
						'payment_gateway'		=>	$payment_gateway
					]
				];

			}

		}

		return $dropdown;

	}

	/**
	 * refundsFetchDropdownOptions function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function refundsFetchDropdownOptions(int $company_id, int $invoice_id) : array {
		$data = $this->invoice_repository->fetchDataForDropdownOptions($company_id, $invoice_id);

		$payments = $this->assembleDropDownData($data['payments']);
		$credits = $this->assembleDropDownData($data['credits']);
		return array_merge($payments, $credits);

	}

	/**
	 * fetchAllRefunds function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchAllRefunds(int $company_id, int $invoice_id) : array {

		$refund_logs = $this->invoice_repository->fetchRefundLogs($company_id, $invoice_id, [
			'refund_logs.id',
			'refund_logs.payment_id',
			'refund_logs.credit_id',
			'refund_logs.amount',
			'refund_logs.created_at',
			'currencies.code',
		]);

		$refunds = [];

		foreach($refund_logs as $log){

			$type = 'Payment';

			if($log->credit_id){
				$type = 'Credit';
			}

			$refunds[] = [
				'id'		=>	$log->id,
				'type'		=>	$type,
				'amount'	=>	$log->amount,
				'currency'	=>	$log->code,
				'issued_on'	=>	Carbon::parse($log->created_at, 'UTC')->toISOString(),
			];

		}

		return $refunds;

	}

	/**
	 * validateIssueRefunds function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateIssueRefunds(Request $request) : bool {
		return $this->invoice_validation_service->validateIssueRefunds($request);
	}

	/**
	 * calculateToBeRefunded function
	 *
	 * @param string $applied
	 * @param string $applied_refund
	 * @param string|null $user_applied
	 * @return string
	 */
	private function calculateToBeRefunded(string $applied, string $applied_refund, ?string $user_applied = null) : string {

		$applied = BigDecimal::of($applied);
		$to_be_refunded = BigDecimal::of($applied_refund);

		if($user_applied){
			$to_be_refunded = $to_be_refunded->plus(BigDecimal::of($user_applied));
		}else{
			$to_be_refunded = $applied;
		}

		if($to_be_refunded->isGreaterThan($applied)){
			$to_be_refunded = $applied;
		}
		
		return $to_be_refunded->toScale(2, RoundingMode::HalfUp)->__toString();

	}


	/**
	 * addUserAppliedAmount function
	 *
	 * @param array $refund_entries
	 * @param array $payment_or_credit
	 * @return array
	 */
	private function addUserAppliedAmount(array $refund_entries, array $payment_or_credit) : array {

		$entries = [];

		foreach($refund_entries as $entry){
			foreach($payment_or_credit as $pc_entry){
				if((int) $entry['meta']['id'] === (int) $pc_entry['id']){

					$temp = $pc_entry;
					$temp['user_applied'] = $entry['meta']['filled_amount'];
					$entries[] = $temp;

				}
			}
		}

		return $entries;

	} 

	/**
	 * normalizeRefundEntries function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param boolean $full_refund
	 * @param array $refund_entries
	 * @return array
	 */
	private function normalizeRefundEntries(int $company_id, int $invoice_id, bool $full_refund, array $refund_entries) : array {

		$entries['payments_offline'] = [];
		$entries['payments_online'] = [];
		$entries['credits'] = [];

		if($full_refund){

			$data = $this->invoice_repository->fetchDataForDropdownOptions($company_id, $invoice_id);
			$payments = $data['payments'];
			$credits = $data['credits'];

		}else{

			$ledger_ids = [];
			foreach($refund_entries as $entry){
				$id = $entry['meta']['id'];
				$ledger_ids[] = $id;
			}

			$credits = $this->invoice_repository->fetchCreditDataForRefundEntries($company_id, $invoice_id, $ledger_ids, [
				'invoice_ledger.id',
				'credits.currency_id',
				'invoice_ledger.credit_id',
				'invoice_ledger.total_applied_refund',
				'invoice_ledger.total_applied'
			]);

			$payments = $this->invoice_repository->fetchPaymentDataForRefundEntries($company_id, $invoice_id, $ledger_ids, [
				'invoice_ledger.id',
				'invoice_ledger.payment_id',
				'transactions.payment_gateway',
				'transactions.refund_token',
				'payments.currency_id',
				'transactions.id as transaction_id',
				'invoice_ledger.total_applied_refund',
				'invoice_ledger.total_applied'
			]);

			//weave
			$credits = $this->addUserAppliedAmount($refund_entries, $credits->toArray());
			$payments = $this->addUserAppliedAmount($refund_entries, $payments->toArray());

		}

		foreach($payments as $payment){

			$user_applied = $payment['user_applied'] ?? null;

			$to_be_refunded = $this->calculateToBeRefunded($payment['total_applied'], $payment['total_applied_refund'], $user_applied);

			$element =  [
				'ledger_id'				=>	$payment['id'],
				'payment_id'			=>	$payment['payment_id'],
				'amount'				=>	$to_be_refunded,
				'original_amount'		=>	$user_applied ?? $payment['total_applied'],
				'payment_gateway'		=>	$payment['payment_gateway'],
				'transaction_id'		=>	$payment['transaction_id'],
				'currency_id'			=>	$payment['currency_id'],
				'refund_token'			=>	$payment['refund_token']
			];

			if($payment['payment_gateway']){
				$entries['payments_online'][] = $element;
			}else{
				$entries['payments_offline'][] = $element;
			}

			
		}

		foreach($credits as $credit){

			$user_applied = $credit['user_applied'] ?? null;
			$to_be_refunded = $this->calculateToBeRefunded($credit['total_applied'], $credit['total_applied_refund'], $user_applied);

			$entries['credits'][] = [
				'ledger_id'			=>	$credit['id'],
				'credit_id'			=>	$credit['credit_id'],
				'currency_id'		=>	$credit['currency_id'],
				'amount'			=>	$to_be_refunded,
				'original_amount'	=>	$user_applied ?? $credit['total_applied']
			];
		}

		return $entries;

	}

	/**
	 * assembleLedgerRows function
	 *
	 * @param Collection $ledger_entries
	 * @param string $type
	 * @return array
	 */
	private function assembleLedgerRows(int $company_id, Collection $ledger_entries, string $type) : array {

		$pc_rows = [];

		$id_column_text = $type.'_id';

		//calculate refund amount per entry
		foreach($ledger_entries as $ledger_entry){

			$key = 'pc_entry_'.$ledger_entry->{$id_column_text};

			if(!isset($pc_rows[$key])){
				$pc_rows[$key] = [
					'amount'		=>	BigDecimal::of(0),
					$id_column_text	=>	$ledger_entry->{$id_column_text},
				];
			}

			$pc_rows[$key]['amount'] = $pc_rows[$key]['amount']->plus(BigDecimal::of($ledger_entry->total_applied_refund));

		}
		
		$final_rows = [];

		
		$payments = null;

		if($type === 'payment'){
			$payment_ids = array_column($pc_rows, $id_column_text);
			$payments = $this->invoice_repository->fetchPaymentByIds($company_id, $payment_ids, ['id', 'unapplied_amount_refund']);
		}
		

		foreach($pc_rows as $pc_row){

			$temp_amount = $pc_row['amount'];
			$applied_refund_amount = $pc_row['amount'];

			if($type === 'payment'){
				foreach($payments as $payment){
					if((int) $payment->id === (int) $pc_row[$id_column_text]){
						$already_refunded = BigDecimal::of($payment->unapplied_amount_refund);
						$temp_amount = $temp_amount->plus($already_refunded);
					}
				}
			}

			$final_rows[] = [
				'amount'				=>	$temp_amount->toScale(2, RoundingMode::HalfUp)->__toString(),
				$id_column_text			=>	$pc_row[$id_column_text],
				'applied_refund_amount' => 	$applied_refund_amount->toScale(2, RoundingMode::HalfUp)->__toString()
			];
		}

		$pc_rows = null;

		return $final_rows;

	}

	/**
	 * assembeCreditRowsForUpdate function
	 *
	 * @param Collection $db_credits
	 * @param array $final_rows
	 * @return array
	 */
	private function assembeCreditRowsForUpdate(Collection $db_credits, array $final_rows) : array {

		$update_db_credits = [];

		foreach($db_credits as $db_credit){

			foreach($final_rows as $final_row){

				if((int) $final_row['credit_id'] === (int) $db_credit->id){

					$applied_amount = BigDecimal::of($db_credit->applied_amount);
					$left_to_apply = BigDecimal::of($db_credit->amount_left_to_be_applied);

					$calculated_amount = BigDecimal::of($final_row['amount']);

					$applied_amount = $applied_amount->minus($calculated_amount);
					$left_to_apply = $left_to_apply->plus($calculated_amount);

					if($left_to_apply->isGreaterThan(BigDecimal::of($db_credit->amount))){
						$left_to_apply = BigDecimal::of($db_credit->amount);
					}

					if($applied_amount->isLessThan(BigDecimal::of(0))){
						$applied_amount = BigDecimal::of(0);
					}

					$status = CreditStatus::NOT_APPLIED->value;

					if($applied_amount->isEqualTo(BigDecimal::of($db_credit->amount))){
						$status = CreditStatus::APPLIED->value;
					}else if($applied_amount->isLessThan(BigDecimal::of($db_credit->amount)) && $applied_amount->isGreaterThan(BigDecimal::of(0))){
						$status = CreditStatus::PARTIALLY_APPLIED->value;
					}

					$applied_amount = $applied_amount->toScale(2, RoundingMode::HalfUp)->__toString();
					$left_to_apply = $left_to_apply->toScale(2, RoundingMode::HalfUp)->__toString();
					
					

					$update_db_credits[] = [
						'id'						=>	$db_credit->id,
						'applied_amount'			=>	$applied_amount,
						'amount_left_to_be_applied'	=>	$left_to_apply,
						'status'					=>	$status,
						'updated_at'				=>	Carbon::now()->format('Y-m-d H:i:s')
					];

				}

			}

		}

		return $update_db_credits;

	}

	/**
	 * processRefunds function
	 *
	 * @param integer $company_id
	 * @param array $credits_or_payments
	 * @param string $type
	 * @return void
	 */
	private function processRefunds(int $company_id, array $credits_or_payments, string $type) : void {

		$this->invoice_repository->updateLedgerForRefunds($company_id, $credits_or_payments, $type);
		
		$credit_or_payment_ids = array_column($credits_or_payments, $type.'_id');
		$ledger_entries = $this->invoice_repository->fetchLedgerEntriesForRefundAmountSum($company_id, $credit_or_payment_ids, $type, [$type.'_id', 'total_applied_refund']);

		$final_rows = $this->assembleLedgerRows($company_id, $ledger_entries, $type);
		
		if($type === 'credit'){

			//update credits row db here
			$db_credits = $this->invoice_repository->fetchCreditsByIds($company_id, $credit_or_payment_ids);
			$update_db_credits = $this->assembeCreditRowsForUpdate($db_credits, $final_rows);
			$this->invoice_repository->updateCredits($update_db_credits);

		}else{
			
			//for payments
			$this->invoice_repository->updatePaymentsForRefunds($final_rows);

		}

	}

	/**
	 * insertRefundLogs function
	 *
	 * @param array $data
	 * @return void
	 */
	private function insertRefundLogs(int $company_id, int $invoice_id, array $data) : void {

		$logs = [];

		foreach($data as $row){
			$logs[] = [
				'company_id'	=>	$company_id,
				'invoice_id'	=>	$invoice_id,
				'payment_id'	=>	$row['payment_id'] ?? null,
				'credit_id'		=>	$row['credit_id'] ?? null,
				'amount'		=>	$row['original_amount'],
				'currency_id'	=>	$row['currency_id'],
				'created_at'	=>	Carbon::now()->format('Y-m-d H:i:s'),
				'updated_at'	=>	Carbon::now()->format('Y-m-d H:i:s')
			];
		}

		$this->invoice_repository->insertRefundLogs($logs);

	}

	/**
	 * updateInvoiceRefundAmount function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @return void
	 */
	private function updateInvoiceRefundAmount(int $company_id, array $invoice_ids) : void {

		$ledger_entries = $this->invoice_repository->fetchLedgerEntriesByInvoiceIds($company_id, $invoice_ids);
		
		//assemble entries
		$raw_invoices_data = [];
		foreach($ledger_entries as $entry){

			if(!isset($raw_invoices_data['inv_'.$entry->invoice_id])){

				$raw_invoices_data['inv_'.$entry->invoice_id] = [
					'id'				=>	$entry->invoice_id,
					'refunded_amount'	=>	BigDecimal::of(0)
				];

			}

			$raw_invoices_data['inv_'.$entry->invoice_id]['refunded_amount'] = $raw_invoices_data['inv_'.$entry->invoice_id]['refunded_amount']->plus($entry->total_applied_refund);

		}

		$invoice_data = [];

		$invoices = $this->invoice_repository->fetchInvoiceByIds($company_id, $invoice_ids, ['id', 'total', 'status', 'balance_due']);

		$zero = BigDecimal::of(0);

		foreach($raw_invoices_data as $element){

			foreach($invoices as $invoice){

				if((int) $invoice->id === $element['id']){

					$status = $invoice->status;

					$total = BigDecimal::of($invoice->total);
					$balance_due = BigDecimal::of($invoice->balance_due);
					

					if($total->isEqualTo($element['refunded_amount'])){
						$status = InvoiceStatus::REFUNDED->value;
					}else if($total->isGreaterThan($element['refunded_amount'])){
						$status = InvoiceStatus::PARTIALLY_REFUNDED->value;
					}else if($total->isGreaterThan($element['refunded_amount']) && $element['refunded_amount']->isEqualTo($zero) && $balance_due->isEqualTo($zero)){
						$status = InvoiceStatus::PAID->value;
					}else if($total->isGreaterThan($element['refunded_amount']) && $element['refunded_amount']->isEqualTo($zero) && $balance_due->isGreaterThan($zero) && $balance_due->isLessThan($total)){
						$status = InvoiceStatus::PARTIALLY_PAID->value;
					}


					$invoice_data[] = [
						'id'				=>	$element['id'],
						'status'			=>	$status,
						'refunded_amount'	=>	$element['refunded_amount']->toScale(2, RoundingMode::HalfUp)->__toString(),
					];

				}

			}
			
		}

		$raw_invoices_data = null;

		$this->invoice_repository->updateInvoicesForRefundedAmount($company_id, $invoice_data);

	}

	/**
	 * updateTransactionsForRefunds function
	 *
	 * @param integer $company_id
	 * @param array $payment_ids
	 * @return void
	 */
	private function updateTransactionsForRefunds(int $company_id, array $payment_ids) : void {

		$payments = $this->invoice_repository->fetchPaymentByIds($company_id, $payment_ids, ['id', 'refunded_amount', 'transaction_id']);

		$records = [];

		foreach($payments as $payment){
			
			if($payment->transaction_id !== null){

				$key = 'transaction_'.$payment->transaction_id;

				if(!isset($records[$key])){
					$records[$key] = [
						'id'				=>	$payment->transaction_id,
						'refunded_amount'	=>	BigDecimal::of(0),
					];
				}

				$records[$key]['refunded_amount'] = $records[$key]['refunded_amount']->plus(BigDecimal::of($payment->refunded_amount));
		
			}

		}

		$final_records = [];

		foreach($records as $record){
			$final_records[] = [
				'id'				=>	$record['id'],
				'refunded_amount'	=>	$record['refunded_amount']->toScale(2, RoundingMode::HalfUp)->__toString()
			];
		}

		$records = null;

		$this->invoice_repository->updateTransactions($company_id, $final_records);

	}

	/**
	 * issueRefunds function
	 *
	 * @param integer $company_id
	 * @param boolean $full_refund
	 * @param array $refund_entries
	 * @return void
	 */
	public function issueRefunds(int $company_id, int $invoice_id, bool $full_refund, array $refund_entries) : void {

		/**
		 * amount
		 * gateway
		 * refund token
		 * type : credit or payment
		 * process credits first
		 * then offline payments
		 * then online payments
		 */

		$data = $this->normalizeRefundEntries($company_id, $invoice_id, $full_refund, $refund_entries);
		
		logger($data);
		//proceed to refund.
		DB::transaction(function() use ($company_id, $invoice_id, $data)  {

			$credits = $data['credits'];
			$offline_payments = $data['payments_offline'];
			$online_payments = $data['payments_online'];
			
			$this->processRefunds($company_id, $credits, 'credit');
			$this->processRefunds($company_id, $offline_payments, 'payment');
			$this->processRefunds($company_id, $online_payments, 'payment');
			$this->insertRefundLogs($company_id, $invoice_id, $credits);
			$this->insertRefundLogs($company_id, $invoice_id, $offline_payments);
			$this->insertRefundLogs($company_id, $invoice_id, $online_payments);

			$this->updateInvoiceRefundAmount($company_id, [$invoice_id]);

			if(!empty($online_payments)){
				$payment_ids = array_column($online_payments, 'payment_id');
				$this->updateTransactionsForRefunds($company_id, $payment_ids);
			}
			

			DB::afterCommit(function() use ($company_id, $invoice_id){
				GenerateInvoiceSnapshotJob::dispatch($company_id, $invoice_id, true);
			});

		});
		



	}

}