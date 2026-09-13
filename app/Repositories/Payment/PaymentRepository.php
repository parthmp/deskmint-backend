<?php

namespace App\Repositories\Payment;

use App\Helpers\General;
use App\Models\Invoice;
use App\Models\InvoiceLedger;
use App\Models\Payment;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Repositories\Credit\CreditRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentRepository {

	public function __construct(
		private CreditRepository $credit_repository
	){}

	/**
	 * fetchById function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return Payment|null
	 */
	public function fetchById(int $company_id, int $id) : ?Payment {
		return Payment::where([['id', '=', $id], ['company_id', '=', $company_id]])->first();
	}

	/**
	 * createOrUpdate function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer $currency_id
	 * @param string $amount
	 * @param string $applied_amount
	 * @param string $amount_left_to_apply
	 * @param integer $status
	 * @param integer $payment_type_id
	 * @param string $payment_number
	 * @param integer|null $transaction_id
	 * @param integer|null $id
	 * @return Payment
	 */
	public function createOrUpdate(int $company_id, int $client_id, int $currency_id, string $amount, string $applied_amount, string $amount_left_to_apply, int $status, int $payment_type_id, string $payment_number, ?int $transaction_id = null, ?int $id = null) : Payment {

		if(!$id){
			$payment = new Payment();
			$payment->company_id = $company_id;
		}else{
			$payment = $this->fetchById((int) $company_id, (int) $id);
		}
		
		$payment->client_id = $client_id;
		$payment->payment_number = $payment_number;
		$payment->transaction_id = $transaction_id;
		$payment->payment_type_id = $payment_type_id;
		$payment->currency_id = $currency_id;
		$payment->status = $status;
		$payment->amount = $amount;
		$payment->applied_amount = $applied_amount;
		$payment->amount_left_to_be_applied = $amount_left_to_apply;
		$payment->save();

		return $payment;

	}

	/**
	 * fetchAppliedPaymentInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @return array
	 */
	public function fetchAppliedPaymentInvoices(int $company_id, int $payment_id) : array {

		$invoices = Payment::select('il.applied_amount_from_payments as applied_amount', 'invoices.invoice_number', 'il.created_at as applied_on')
							->join('invoice_ledger as il', 'il.payment_id', '=', 'payments.id')
							->join('invoices', 'il.invoice_id', '=', 'invoices.id')
							->where([['payments.company_id', '=', $company_id], ['payments.id', '=', $payment_id]])->get();
		
		$invoices = $invoices->toArray();

		foreach($invoices as $key => $entry){
			foreach($entry as $sub_key => $sub_entry){
				if(General::isMySQLDateTime($sub_entry)){
					$invoices[$key][$sub_key] = Carbon::parse($sub_entry)->toISOString();
				}
			}
		}

		return $invoices;

	}

	/**
	 * fetchPaymentForEdit function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return array
	 */
	public function fetchPaymentForEdit(int $company_id, int $id) : array {
		
		$payment = Payment::select('payments.*', 'clients.full_name as full_name', 'payment_currencies.code as payment_currency', 'client_currencies.code as client_currency')->join('clients', 'clients.id', '=', 'payments.client_id')->join('currencies as payment_currencies', 'payments.currency_id', '=', 'payment_currencies.id')->join('currencies as client_currencies', 'clients.currency_id', '=', 'client_currencies.id')->where([['payments.company_id', '=', $company_id], ['payments.id', '=', $id]])->first();
		
		$payment = $payment->toArray();
		
		$payment['status_text'] = PaymentStatus::NOT_APPLIED->label();
		if((int) $payment['status'] === PaymentStatus::PARTIALLY_APPLIED->value){
			$payment['status_text'] = PaymentStatus::PARTIALLY_APPLIED->label();
		}else if((int) $payment['status'] === PaymentStatus::APPLIED->value){
			$payment['status_text'] = PaymentStatus::APPLIED->label();
		}

		return $payment;
		
	}

	/**
	 * fetchPaymentIdWithTransactionId function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @return array
	 */
	public function fetchPaymentIdWithTransactionId(int $company_id, array $ids) : array {

		$payments = Payment::where('company_id', '=', $company_id)->whereIn('id', $ids)->get()->toArray();
		return $payments;

	}

	/**
	 * ifAnyPaymentsAreApplied function
	 *
	 * @param array $ids
	 * @return boolean
	 */
	public function ifAnyPaymentsAreApplied(array $ids) : bool {
		$counted = Payment::where(function($q){
			$q->where('status', '=', PaymentStatus::APPLIED->value)->orWhere('status', '=', PaymentStatus::PARTIALLY_APPLIED->value);
		})->whereIn('id', $ids)->count();
		return (int) $counted > 0;
	}

	/**
	 * deleteMultiplePayments function
	 *
	 * @param array $ids
	 * @return boolean
	 */
	public function deleteMultiplePayments(array $ids) : bool {
		return Payment::whereIn('id', $ids)->delete();
	}

	/**
	 * fetchLedgerEntries function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @param array $not_in_ids
	 * @return array
	 */
	public function fetchLedgerEntries(int $company_id, int $payment_id, array $not_in_ids) : array {
		$entries = InvoiceLedger::select('invoice_id as invoice_id', 'applied_amount_from_payments as applied_amount')->where([['company_id', '=', $company_id], ['payment_id', '=', $payment_id]])->whereNotIn('invoice_id', $not_in_ids)->get()->toArray();
		return $entries;
	}

	/**
	 * fetchAppliedPaymentsLedger function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @param array $invoice_ids
	 * @return Collection
	 */
	public function fetchAppliedPaymentsLedger(int $company_id, int $payment_id, array $invoice_ids) : Collection {
		return InvoiceLedger::select('id as ledger_id', 'applied_amount_from_payments as applied_payment', 'invoice_id as invoice_id')->where([['company_id', '=', $company_id], ['payment_id', '=', $payment_id]])->whereIn('invoice_id', $invoice_ids)->get();
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

		$conditions = [['payment_number', '=', $payment_number], ['company_id', '=', $company_id]];

		if($ignore_id){
			array_push($conditions, ['id', '<>', $ignore_id]);
		}

		$found = Payment::where($conditions)->count();

		return (int) $found > 0;
	}

}