<?php

namespace App\Modules\Payment\Traits;

use App\Enums\PaymentRequests\PaymentRequestStatus;
use App\Jobs\GenerateInvoiceJob;
use App\Models\Invoice;
use App\Models\InvoiceLedger;
use App\Models\InvoiceSnapshot;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\TransactionReference;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Notification;
use App\Modules\Payment\Enums\InvoiceStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use App\Modules\InvoiceGeneration\InvoiceSnapshot as Snapshot;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Collection;

trait UpdateInvoiceForTransaction {

	/**
	 * fetchInvoiceById function
	 *
	 * @param integer $invoice_id
	 * @return ?Invoice
	 */
	public function fetchInvoiceById(int $invoice_id) : ?Invoice {
		return Invoice::where('id', '=', $invoice_id)->first();
	}

	/**
	 * sumOfAmounts function
	 *
	 * @param Collection $amounts
	 * @return string
	 */
	public function sumOfAmounts(Collection $amounts) : string {

		$total = BigDecimal::of(0);

		foreach($amounts as $amount){
			$total = $total->plus($amount->total_applied);
		}

		return $total->toScale(2, RoundingMode::HalfUp)->__toString();

	}

	/**
	 * fetchTransactionRefByTransactionId function
	 *
	 * @param integer $transaction_id
	 * @return TransactionReference
	 */
	private function fetchTransactionRefByTransactionId(int $transaction_id) : TransactionReference {
		return TransactionReference::where('transaction_id', '=', $transaction_id)->first();
	}

	/**
	 * fetchAppliedInvoiceAmounts function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return Collection
	 */
	private function fetchAppliedInvoiceAmounts(int $company_id, int $invoice_id) : Collection {
		return InvoiceLedger::select('total_applied')->where([['company_id', '=', $company_id], ['invoice_id', '=', $invoice_id]])->get();
	}

	/**
	 * insertPayment function
	 *
	 * @param array $data
	 * @return Payment
	 */
	private function insertPayment(array $data) : Payment {
		$payment = new Payment();
		$payment->company_id = $data['company_id'];
		$payment->client_id = $data['client_id'];
		$payment->transaction_id = $data['transaction_id'];
		$payment->currency_id = $data['currency_id'];
		$payment->status = $data['status'];
		$payment->amount = $data['amount'];
		$payment->applied_amount = $data['applied_amount'];
		$payment->amount_left_to_be_applied = $data['amount_left_to_be_applied'];
		$payment->save();
		return $payment;
	}

	/**
	 * insertInvoiceLedgerEntry function
	 *
	 * @param array $data
	 * @return InvoiceLedger
	 */
	private function insertInvoiceLedgerEntry(array $data) : InvoiceLedger {
		
		$ledger = new InvoiceLedger();

		$ledger->company_id = $data['company_id'];
		$ledger->invoice_id = $data['invoice_id'];
		$ledger->payment_id = $data['payment_id'];
		$ledger->credit_id = $data['credit_id'];
		$ledger->applied_amount_from_payments = $data['applied_amount_from_payments'];
		$ledger->applied_amount_from_credits = $data['applied_amount_from_credits'];
		$ledger->total_applied = $data['total_applied'];
		$ledger->save();

		return $ledger;
	}

	/**
	 * updateAndApplyPayment function
	 *
	 * @param Transaction $transaction
	 * @param boolean $notify
	 * @return boolean
	 */
	public function updateAndApplyPayment(Transaction $transaction, bool $notify = true) : bool {
		
		$ref = $this->fetchTransactionRefByTransactionId($transaction->id);

		if($ref->invoice_id !== null && $ref->payment_request_id === null){
			return $this->updateInvoiceStatusForPayments($transaction, $ref, $notify);
		}else if($ref->invoice_id === null && $ref->payment_request_id !== null){
			return $this->updatePaymentRequest($transaction, $ref, $notify);
		}

		return false;

	}

	/**
	 * updatePaymentRequest function
	 *
	 * @param Transaction $transaction
	 * @param boolean $notify
	 * @return boolean
	 */
	public function updatePaymentRequest(Transaction $transaction, TransactionReference $ref, bool $notify = true) : bool {

		$payment = $this->insertPayment([
			'company_id' 				=> $transaction->company_id,
			'client_id' 				=> $ref->client_id,
			'transaction_id' 			=> $ref->transaction_id,
			'currency_id' 				=> $transaction->currency_id,
			'status' 					=> PaymentStatus::NOT_APPLIED->value,
			'amount' 					=> $transaction->amount,
			'applied_amount' 			=> 0,
			'amount_left_to_be_applied' => $transaction->amount,
		]);

		//update payment request
		$pr = PaymentRequest::where('id', '=', $ref->payment_request_id)->withTrashed()->first();
		$pr->amount = $transaction->amount;
		$pr->status = PaymentRequestStatus::COMPLETED->value;
		$pr->transaction_id = $transaction->id;
		$pr->paid_at = now();
		$pr->save();

		if($payment){
			return true;
		}

		return false;

	}

	/**
	 * updateInvoiceStatusForPayments function
	 *
	 * @param Transaction $transaction
	 * @param boolean $notify
	 * @return boolean
	 */
	private function updateInvoiceStatusForPayments(Transaction $transaction, TransactionReference $ref, bool $notify = true) : bool {

		$invoice = $this->fetchInvoiceById($ref->invoice_id);

		$transaction_amount = BigDecimal::of($transaction->amount);

		$balance_due = BigDecimal::of($invoice->balance_due);
		
		$diff_payment_amount = $transaction_amount->minus($balance_due);

		$payment_status = PaymentStatus::NOT_APPLIED->value;
		if($diff_payment_amount->isEqualTo(BigDecimal::of(0)) || $diff_payment_amount->isLessThan(BigDecimal::of(0))){
			$payment_status = PaymentStatus::APPLIED->value;
		}else if($diff_payment_amount->isGreaterThan(BigDecimal::of(0))){
			$payment_status = PaymentStatus::PARTIALLY_APPLIED->value;
		}

		$left_to_be_applied = BigDecimal::of(0);
		if($diff_payment_amount->isGreaterThan($left_to_be_applied)){
			$left_to_be_applied = $diff_payment_amount;
		}

		$applied_amount = $transaction_amount->minus($left_to_be_applied);
		$applied_amount_str = $applied_amount->toScale(2, RoundingMode::HalfUp)->__toString();
		
		//insert whole received payment.
		$payment = $this->insertPayment([
			'company_id' 				=> $transaction->company_id,
			'client_id' 				=> $ref->client_id,
			'transaction_id' 			=> $ref->transaction_id,
			'currency_id' 				=> $transaction->currency_id,
			'status' 					=> $payment_status,
			'amount' 					=> $transaction->amount,
			'applied_amount' 			=> $applied_amount_str,
			'amount_left_to_be_applied' => $left_to_be_applied->toScale(2, RoundingMode::HalfUp)->__toString(),
		]);

		//apply it to the invoice.
		$ledger = $this->insertInvoiceLedgerEntry([
			'company_id' 					=> $transaction->company_id,
			'invoice_id' 					=> $ref->invoice_id,
			'payment_id' 					=> $payment->id,
			'credit_id' 					=> null,
			'applied_amount_from_payments' 	=> $applied_amount_str,
			'applied_amount_from_credits' 	=> 0,
			'total_applied' 				=> $applied_amount_str,
		]);


		
		$total = BigDecimal::of($invoice->total);

		$all_applied_amounts_of_invoice = $this->fetchAppliedInvoiceAmounts($transaction->company_id, $ref->invoice_id);

		//$paid_amount = $this->sumOfAmounts($this->fetchAmountsOfTransactionsByInvoiceId($transaction->company_id, $transaction->invoice_id));
		$paid_amount = $this->sumOfAmounts($all_applied_amounts_of_invoice);

		$paid_amount = BigDecimal::of($paid_amount);

		$balance_due = $total->minus($paid_amount);
		if($balance_due->isLessThan(BigDecimal::of(0))){
			$balance_due = BigDecimal::of(0);
		}

		$paid_in_full = $paid_amount->isEqualTo($total);
		$overpaid = $paid_amount->isGreaterThan($total);
		$partially_paid = $paid_amount->isLessThan($total) && $paid_amount->isGreaterThan(BigDecimal::of(0));

		if((int) $invoice->status === (int) InvoiceStatus::CANCELLED->value && $notify){
			app(Notification::class)->notify($invoice->company_id, NotificationType::INVOICE_CANCELLED_PAID, 'Your customer paid cancelled invoice', 'Invoice: '. $invoice->invoice_number.' was cancelled and customer made a payment towards it. Transaction id: '.$transaction->id.', Identifer: '.$transaction->token_id_identifier, []);
		}

		if($overpaid && $notify){
			app(Notification::class)->notify($invoice->company_id, NotificationType::INVOICE_OVERPAID, 'Your customer overpaid invoice', 'Invoice: '. $invoice->invoice_number.' was overpaid by your customer. Transaction id: '.$transaction->id.', Identifer: '.$transaction->token_id_identifier, []);
		}

		$status_indicator = InvoiceStatus::SENT->value;

		if($paid_in_full || $overpaid){
			$status_indicator = InvoiceStatus::PAID->value;
		}else if($partially_paid){
			$status_indicator = InvoiceStatus::PARTIALLY_PAID->value;
		}

		$invoice->status = $status_indicator;
		$invoice->balance_due = $balance_due->toScale(2, RoundingMode::HalfUp)->__toString();

		return $invoice->save();

	}

	/**
	 * updateInvoiceSnapshot function
	 *
	 * @param integer $invoice_id
	 * @return void
	 */
	private function updateInvoiceSnapshot(int $invoice_id) : void {
		
		$invoice = $this->fetchInvoiceById($invoice_id);
		
		if($invoice !== null){
			$snapshot = app(Snapshot::class)
						->setCompanyId($invoice->company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();
			
				InvoiceSnapshot::updateOrCreate(
					['invoice_id' 	=> $invoice->id],
					['snapshot' 	=> $snapshot]
				);

			//regenerate pdf
			
			GenerateInvoiceJob::dispatch($invoice->company_id, $invoice->id);
		}
		
	}

}