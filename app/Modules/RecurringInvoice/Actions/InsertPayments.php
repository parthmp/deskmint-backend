<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\RecurringInvoice\DB\DB;
use App\Modules\RecurringInvoice\Exceptions\RecurringInvoiceModuleException;
use Illuminate\Support\Str;

class InsertPayments {

	private array $invoice_ledger_rows = [];

	public function __construct(
		private DB $db
	){}

	
	/**
	 * filterRecurringInvoiceData function
	 *
	 * @param array $recurring_invoices_data
	 * @param array $inserted
	 * @param integer $recurring_invoice_id
	 * @return array
	 */
	private function filterRecurringInvoiceData(array $recurring_invoices_data, array $inserted, int $recurring_invoice_id) : array {
		
		$record = $this->filterInsertedData($inserted, $recurring_invoice_id);
		if(empty($record)){
			return [];
		}

		foreach($recurring_invoices_data as $recurring_entry){
			if((int) $recurring_entry['id'] === (int) $recurring_invoice_id){
				return $recurring_entry;
			}
		}

		return [];

	}

	/**
	 * filterInsertedData function
	 *
	 * @param array $inserted
	 * @param integer $recurring_invoice_id
	 * @return array
	 */
	private function filterInsertedData(array $inserted, int $recurring_invoice_id) : array {

		foreach($inserted as $entry){
			if((int) $entry['recurring_invoice_id'] === (int) $recurring_invoice_id){
				return $entry;
			}
		}

		return [];

	}

	/**
	 * paymentEntry function
	 *
	 * @param array $recurring_entry
	 * @param integer $invoice_id
	 * @param integer|null $transaction_id
	 * @return array
	 */
	private function paymentEntry(array $recurring_entry, int $invoice_id, ?int $transaction_id = null) : array {

		$uuid = Str::uuid()->toString();

		//generating invoice_lodger entries same time to avoid additional loops later on.
		$this->invoice_ledger_rows[] = [
			'payment_number'					=>	$uuid,
			'company_id'						=>  $recurring_entry['company_id'],
			'invoice_id'						=>  $invoice_id,
			'payment_id'						=>  null,
			'credit_id'							=>  null,
			'applied_amount_from_payments'		=>  $recurring_entry['total'],
			'applied_amount_from_credits'		=>  0,
			'total_applied'						=>  $recurring_entry['total'],
			'created_at'						=>	now(),
			'updated_at'						=>	now()
		];

		return [
				'payment_number'			=>	$uuid,
				'company_id'				=>	$recurring_entry['company_id'],
				'client_id'					=>	$recurring_entry['client_id'],
				'transaction_id'			=>	$transaction_id,
				'payment_type_id'			=>	$recurring_entry['payment_type_id'],
				'currency_id'				=>	$recurring_entry['currency_id'],
				'status'					=>	PaymentStatus::APPLIED->value,
				'amount'					=>	$recurring_entry['total'],
				'applied_amount'			=>	$recurring_entry['total'],
				'amount_left_to_be_applied'	=>	0,
				'created_at'				=>	now(),
				'updated_at'				=>	now()
			];

	}

	/**
	 * assembleOnlineData function
	 *
	 * @param array $recurring_invoices_data
	 * @param array $inserted
	 * @param array $online_transaction_data
	 * @return array
	 */
	private function assembleOnlineData(array $recurring_invoices_data, array $inserted, array $online_transaction_data) : array {

		$online_invoice_ids = [];
		$insert = [];

		foreach($online_transaction_data as $entry){

			$recurring_entry = $this->filterRecurringInvoiceData($recurring_invoices_data, $inserted, (int) $entry['recurring_invoice_id']);

			if(empty($recurring_entry)){
				throw new RecurringInvoiceModuleException("could not find recurring entry.", "not_found_recurring_entry", (int) config('global.error_code'));
			}

			$inserted_entry = $this->filterInsertedData($inserted, (int) $entry['recurring_invoice_id']);

			if(empty($inserted_entry)){
				throw new RecurringInvoiceModuleException("could not find inserted entry.", "not_found_inserted_entry", (int) config('global.error_code'));
			}

			$online_invoice_ids[] = $inserted_entry['id'];

			$insert[] = $this->paymentEntry($recurring_entry, (int) $inserted_entry['id'], (int) $entry['transaction_id']);

		}

		return [
			'insert'				=>	$insert,
			'online_invoice_ids'	=>	$online_invoice_ids
		];

	}

	/**
	 * assembleOfflineData function
	 *
	 * @param array $recurring_invoices_data
	 * @param array $inserted
	 * @param array $invoice_ids_in_online
	 * @return array
	 */
	private function assembleOfflineData(array $recurring_invoices_data, array $inserted, array $invoice_ids_in_online) : array {

		$insert = [];

		foreach($inserted as $inserted_entry){

			if(!in_array($inserted_entry['id'], $invoice_ids_in_online)){

				$recurring_entry = $this->filterRecurringInvoiceData($recurring_invoices_data, $inserted, (int) $inserted_entry['recurring_invoice_id']);

				if(empty($recurring_entry)){
					throw new RecurringInvoiceModuleException("could not find recurring entry.", "not_found_recurring_entry", (int) config('global.error_code'));
				}

				if((int) $recurring_entry['mark_paid_automatically'] === 1 && (int) $recurring_entry['payment_gateway'] === PaymentGateway::NONE->value){
					$insert[] = $this->paymentEntry($recurring_entry, (int) $inserted_entry['id']);
				}

				
			}

		}

		return $insert;

	}

	/**
	 * replacePaymentIdForInvoiceLedgerEntries function
	 *
	 * @param array $payment_rows
	 * @return void
	 */
	private function replacePaymentIdForInvoiceLedgerEntries(array $payment_rows) : void {

		for($z = 0 ; $z < count($this->invoice_ledger_rows) ; $z++){

			foreach($payment_rows as $payment_row){
				
				if((string) $this->invoice_ledger_rows[$z]['payment_number'] === (string) $payment_row['payment_number']){
					$this->invoice_ledger_rows[$z]['payment_id'] = $payment_row['id'];
				}

			}

		}

		//unsetting payment_number from invoice_ledger_rows array.
		array_walk($this->invoice_ledger_rows, function(&$row) {
			unset($row['payment_number']);
		});

	}

	/**
	 * insertPayments function
	 *
	 * @param array $recurring_invoices_data
	 * @param array $inserted
	 * @param array|null $online_transaction_data
	 * @return void
	 */
	public function insertPayments(array $recurring_invoices_data, array $inserted, ?array $online_transaction_data = null) : void {
		
		/**
		 * $online_transaction_data should have
		 * $online_transaction_data = [
		 *     [
		 *         'recurring_invoice_id'  => 1,
		 *         'transaction_id'        => 2
		 *     ],
		 *     [
		 *         'recurring_invoice_id'  => 3,
		 *         'transaction_id'        => 4
		 *     ],
		 * ];
		 */

		$payments = [];
		$invoice_ids_in_online = [];

		if($online_transaction_data){

			//this is for online payments
			$data = $this->assembleOnlineData($recurring_invoices_data, $inserted, $online_transaction_data);
			$invoice_ids_in_online = array_merge($invoice_ids_in_online, $data['online_invoice_ids']);
			$payments = array_merge($payments, $data['insert']);

		}
		
		//offline payments.
		$offline_payments = $this->assembleOfflineData($recurring_invoices_data, $inserted, $invoice_ids_in_online);
		$payments = array_merge($payments, $offline_payments);

		if(!empty($payments)){
			$payment_rows = $this->db->insertPayments($payments);
			$this->replacePaymentIdForInvoiceLedgerEntries($payment_rows);
			$this->db->insertInvoiceLedgerEntries($this->invoice_ledger_rows);
		}

		
		

	}

}