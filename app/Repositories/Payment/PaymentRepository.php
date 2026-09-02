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
	 * fetchPaymentWithCurrencyInfo function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @return array
	 */
	public function fetchPaymentWithCurrencyInfo(int $company_id, int $payment_id) : array {
		return Payment::select(
							'payments.id as id',
							'payments.client_id as client_id',
							'payments.amount as amount',
							'payments.amount_left_to_be_applied as left',
							'currencies.code as currency_code',
							'currencies.id as currency_id',
							'clients.full_name as full_name'
						)
						->join('clients', 'clients.id', '=', 'payments.client_id')
						->join('currencies', 'currencies.id', '=', 'payments.currency_id')
						->where([['payments.company_id', '=', $company_id], ['payments.id', '=', $payment_id]])
						->first()
						->toArray();
	}

	/**
	 * fetchAlreadyAppliedInvoicesForPayment function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @return array
	 */
	public function fetchAlreadyAppliedInvoicesForPayment(int $company_id, int $payment_id) : array {
		return Invoice::select('invoices.id as id', 'invoices.invoice_number as invoice', 'invoices.total as total', 'invoices.balance_due as due', 'il.applied_amount_from_payments as amount')->join('invoice_ledger as il', 'il.invoice_id', '=', 'invoices.id')->where([['il.company_id', '=', $company_id], ['il.payment_id', '=', $payment_id]])->get()->toArray();
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
	 * searchInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param integer $payment_id
	 * @param array $applied_ids
	 * @param array $paid_ids
	 * @param string $searched
	 * @return array
	 */
	public function searchInvoices(int $company_id, int $currency_id, int $client_id,  int $payment_id, array $applied_ids, array $paid_ids, string $searched) : array {
		
		$unpaid_invoices_raw = Invoice::select('id as id', 'invoice_number as invoice', 'total as total', 'balance_due as due')->where([['currency_id', '=', $currency_id], ['company_id', '=', $company_id], ['client_id', '=', $client_id]])
			->whereNotIn('id', $applied_ids)
			->where(function($q) {
				$q->where('status', '=', InvoiceStatus::SENT->value)
				->orWhere('status', '=', InvoiceStatus::PARTIALLY_PAID->value);
			})->when($searched, function ($query, $searched) {
				$query->where(function ($q) use ($searched) {

					$q->where('invoice_number', 'like', "%{$searched}%")
					->orWhere('id', 'like', "%{$searched}%")
					->orWhere('balance_due', 'like', "%{$searched}%")
					->orWhere('total', 'like', "%{$searched}%");
				});
			})
			->orderBy('id', 'desc')->limit(50)->get()->toArray();

		$paid_invoices = [];

		if(!empty($paid_ids)){
			
			$paid_invoices = Invoice::select('invoices.id as id', 'invoices.invoice_number as invoice', 'invoices.total as total', 'invoices.balance_due as due', 'il.applied_amount_from_payments as applied_amount')
			->join('invoice_ledger as il', 'il.invoice_id', '=', 'invoices.id')
			->where([['invoices.currency_id', '=', $currency_id], ['invoices.company_id', '=', $company_id], ['invoices.client_id', '=', $client_id], ['il.payment_id', '=', $payment_id]])
			->whereIn('invoices.id', $paid_ids)
			->where('invoices.status', '=', InvoiceStatus::PAID->value)->when($searched, function ($query, $searched) {
				$query->where(function ($q) use ($searched) {

					$q->where('invoices.invoice_number', 'like', "%{$searched}%")
					->orWhere('invoices.id', 'like', "%{$searched}%")
					->orWhere('invoices.balance_due', 'like', "%{$searched}%")
					->orWhere('invoices.total', 'like', "%{$searched}%");
				});
			})
			->orderBy('invoices.id', 'desc')->limit(50)->get()->toArray();

		}

		//weave for unpaid invoices to have partially paid invoices applied_amount.
		$entries = $this->fetchLedgerEntries($company_id, $payment_id, $applied_ids);
		$unpaid_invoices = [];
		foreach($unpaid_invoices_raw as $unpaid_invoice_raw){
			$temp = $unpaid_invoice_raw;
			$temp['applied_amount'] = '';
			foreach($entries as $entry){
				if((int) $entry['invoice_id'] === (int) $unpaid_invoice_raw['id']){
					$temp['applied_amount'] = $entry['applied_amount'];
					break;
				}
			}
			$unpaid_invoices[] = $temp;
		}
		
		
		return [
			'unpaid_invoices'	=>	$unpaid_invoices,
			'paid_invoices'		=>	$paid_invoices
		];

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
	 * resetPayment function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @return Payment
	 */
	public function resetPayment(int $company_id, int $payment_id) : Payment {
		$payment = $this->fetchById($company_id, $payment_id);
		$payment->status = PaymentStatus::NOT_APPLIED->value;
		$payment->applied_amount = 0;
		$payment->amount_left_to_be_applied = $payment->amount;
		$payment->save();
		return $payment;
	}

	/**
	 * updatePaymentForApplying function
	 *
	 * @param Payment $payment
	 * @param integer $status
	 * @param string $applied_amount
	 * @param string $left_amount
	 * @return boolean
	 */
	public function updatePaymentForApplying(Payment $payment, int $status, string $applied_amount, string $left_amount) : bool {
		$payment->status = $status;
		$payment->applied_amount = $applied_amount;
		$payment->amount_left_to_be_applied = $left_amount;
		return $payment->save();
	}

	/**
	 * forceRemoveLedgreEntriesForPayment function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @return void
	 */
	public function forceRemoveLedgreEntriesForPayment(int $company_id, int $payment_id) : void {
		InvoiceLedger::where([['company_id', '=', $company_id], ['payment_id', '=', $payment_id]])->forceDelete();
	}

	/**
	 * insertLedgerEntries function
	 *
	 * @param integer $company_id
	 * @param integer $payment_id
	 * @param array $data
	 * @return void
	 */
	public function insertLedgerEntries(int $company_id, int $payment_id, array $data) : void {

		$insert = [];

		foreach($data as $row){
			$insert[] = [
				'company_id'						=>	$company_id,
				'invoice_id'						=>	(int) $row['invoice_id'],
				'payment_id'						=>	$payment_id,
				'credit_id'							=>	null,
				'applied_amount_from_payments'		=>	$row['applied_amount'],
				'applied_amount_from_credits'		=>	0,
				'total_applied'						=>	$row['applied_amount'],
				'created_at'						=>	now(),
				'updated_at'						=>	now()
			];
		}

		InvoiceLedger::insert($insert);

	}

	/**
	 * fetchInvoicesForPaymentApplying function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @return array
	 */
	public function fetchInvoicesForPaymentApplying(int $company_id, array $ids) : array {
		return $this->credit_repository->fetchInvoicesForCreditApplying($company_id, $ids);
	}

	/**
	 * fetchLedgerForPaymentApplying function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @return array
	 */
	public function fetchLedgerForPaymentApplying(int $company_id, array $ids) : array {
		return $this->credit_repository->fetchLedgerForCreditApplying($company_id, $ids);
	}

	/**
	 * updateInvoicesForPaymentApplying function
	 *
	 * @param array $upsert
	 * @return void
	 */
	public function updateInvoicesForPaymentApplying(array $upsert): void {
		$this->credit_repository->updateInvoicesForCreditApplying($upsert);
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