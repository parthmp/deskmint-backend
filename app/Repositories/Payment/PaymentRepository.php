<?php

namespace App\Repositories\Payment;

use App\Helpers\General;
use App\Models\Payment;
use App\Modules\Payment\Enums\PaymentStatus;
use Carbon\Carbon;

class PaymentRepository {

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
	 * @param integer|null $transaction_id
	 * @param integer|null $id
	 * @return Payment
	 */
	public function createOrUpdate(int $company_id, int $client_id, int $currency_id, string $amount, string $applied_amount, string $amount_left_to_apply, int $status, int $payment_type_id, ?int $transaction_id = null, ?int $id = null) : Payment {

		if(!$id){
			$payment = new Payment();
			$payment->company_id = $company_id;
		}else{
			$payment = $this->fetchById((int) $company_id, (int) $id);
		}
		
		$payment->client_id = $client_id;
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

}