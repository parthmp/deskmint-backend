<?php

namespace App\Services\Invoice;

use App\Enums\Credits\CreditStatus;
use App\Enums\Refunds\RefundStatus;
use App\Helpers\General;
use App\Jobs\GenerateInvoiceJob;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Gateways\Payment;
use App\Repositories\Invoice\InvoiceRepository;
use App\Services\Invoice\Exceptions\InvoiceException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceRefundService {

	public function __construct(
		private InvoiceRepository $invoice_repository
	){}

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
	 * assembleLedgerRows function
	 *
	 * @param Collection $ledger_entries
	 * @param string $type
	 * @return array
	 */
	public function assembleLedgerRows(int $company_id, Collection $ledger_entries, string $type) : array {

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
			//this means it is full refund.
			$to_be_refunded = $applied->minus($to_be_refunded);
		}

		if($to_be_refunded->isGreaterThan($applied)){
			$to_be_refunded = $applied;
		}
		
		return $to_be_refunded->toScale(2, RoundingMode::HalfUp)->__toString();

	}

	/**
	 * getAmounts function
	 *
	 * @param array $payment_or_credit
	 * @param boolean $full_refund
	 * @return array
	 */
	private function getAmounts(array $payment_or_credit, bool $full_refund) : array {

		$user_applied = $payment_or_credit['user_applied'] ?? null;
		
		$to_be_refunded = $this->calculateToBeRefunded($payment_or_credit['total_applied'], $payment_or_credit['total_applied_refund'], $user_applied);

		if($full_refund){
			$amount = $user_applied ?? $payment_or_credit['total_applied'];
			$original_amount = $to_be_refunded;
		}else{
			$amount = $to_be_refunded;
			$original_amount = $user_applied ?? $payment_or_credit['total_applied'];
		}

		return [
			$amount,
			$original_amount
		];

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

			[$amount, $original_amount] = $this->getAmounts($payment, $full_refund);

			if(BigDecimal::of($original_amount)->isGreaterThan(BigDecimal::of(0))){

				$element =  [
					'uuid'					=>	Str::uuid()->toString(),
					'ledger_id'				=>	$payment['id'],
					'payment_id'			=>	$payment['payment_id'],
					'amount'				=>	$amount,
					'original_amount'		=>	$original_amount,
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

		}

		foreach($credits as $credit){

			[$amount, $original_amount] = $this->getAmounts($credit, $full_refund);

			if(BigDecimal::of($original_amount)->isGreaterThan(BigDecimal::of(0))){

				$entries['credits'][] = [
					'ledger_id'			=>	$credit['id'],
					'credit_id'			=>	$credit['credit_id'],
					'currency_id'		=>	$credit['currency_id'],
					'amount'			=>	$amount,
					'original_amount'	=>	$original_amount,
				];

			}
		}

		return $entries;

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

			$uuid = Str::uuid()->toString();

			$logs[] = [
				'uuid'				=>	$uuid,
				'gateway_refund_id'	=>	$row['gateway_refund_id'] ?? null,
				'company_id'		=>	$company_id,
				'invoice_id'		=>	$invoice_id,
				'payment_id'		=>	$row['payment_id'] ?? null,
				'credit_id'			=>	$row['credit_id'] ?? null,
				'amount'			=>	$row['original_amount'],
				'transaction_id'	=>	$row['transaction_id'] ?? null,
				'currency_id'		=>	$row['currency_id'],
				'ARN'				=>	$row['arn'] ?? '',
				'created_at'		=>	Carbon::now()->format('Y-m-d H:i:s'),
				'updated_at'		=>	Carbon::now()->format('Y-m-d H:i:s')
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

		foreach($raw_invoices_data as $element){

			foreach($invoices as $invoice){

				if((int) $invoice->id === $element['id']){

					$total = BigDecimal::of($invoice->total);
					$balance_due = BigDecimal::of($invoice->balance_due);

					$refund_status = General::getRefundStatusForInvoice($total, $balance_due, $element['refunded_amount']);

					$invoice_data[] = [
						'id'				=>	$element['id'],
						'refund_status'		=>	$refund_status,
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
	 * @param integer $invoice_id
	 * @param boolean $full_refund
	 * @param array $refund_entries
	 * @param array|null $webhook_data
	 * @return void
	 */
	public function issueRefunds(int $company_id, int $invoice_id, bool $full_refund, array $refund_entries, ?array $webhook_data = null) : void {
		
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
		$temp_online_payments = $data['payments_online'];

		$online_payments = [];
		$errors = [];

		if($webhook_data === null){

			//call apis
			foreach($temp_online_payments as $temp_online_payment){

				if((int) $temp_online_payment['payment_gateway'] === PaymentGateway::STRIPE->value){
					
					$payment = new Payment((int) $company_id);
					$response = $payment->stripe()->refund((string) $temp_online_payment['refund_token'], (string) $temp_online_payment['original_amount'], (int) $temp_online_payment['ledger_id']);
					
					if($response['refund_data'] !== null){
						$temp_online_payment['gateway_refund_id'] = $response['refund_data']->id;
						$online_payments[] = $temp_online_payment;
					}else{
						$errors[] = $response['error'];
					}

				}

			}
		}else{
			$online_payments = $temp_online_payments;
			$online_payments[0]['gateway_refund_id'] = $webhook_data['gateway_refund_id'];
		}

		$temp_online_payments = null;
		
		$committed = [
			'online' => false,
			'offline' => false,
		];
		
		
		DB::transaction(function() use ($company_id, $invoice_id, $online_payments, &$committed) {
			
			$this->processRefunds($company_id, $online_payments, 'payment');
			$this->insertRefundLogs($company_id, $invoice_id, $online_payments);
			$this->updateInvoiceRefundAmount($company_id, [$invoice_id]);
			$payment_ids = array_column($online_payments, 'payment_id');
			$this->updateTransactionsForRefunds($company_id, $payment_ids);

			DB::afterCommit(function () use (&$committed) {
				$committed['online'] = true;
			});

		});


		//proceed to refund for offline payments.
		DB::transaction(function() use ($company_id, $invoice_id, $data, &$committed)  {

			$credits = $data['credits'];
			$offline_payments = $data['payments_offline'];
			
			$this->processRefunds($company_id, $credits, 'credit');
			$this->processRefunds($company_id, $offline_payments, 'payment');
			
			$this->insertRefundLogs($company_id, $invoice_id, $credits);
			$this->insertRefundLogs($company_id, $invoice_id, $offline_payments);
			
			$this->updateInvoiceRefundAmount($company_id, [$invoice_id]);
			
			DB::afterCommit(function () use (&$committed) {
				$committed['offline'] = true;
			});

		});
		

		if(($committed['online'] && !empty($online_payments)) || ($committed['offline'] && (!empty($data['payments_offline']) || !empty($data['credits'])))){
			GenerateInvoiceJob::dispatch($company_id, $invoice_id, false);
		}

		// logger($errors);

	}

}