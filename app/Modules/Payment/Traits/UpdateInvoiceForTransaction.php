<?php

namespace App\Modules\Payment\Traits;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Invoice;
use App\Models\InvoiceSnapshot;
use App\Models\Transaction;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Notification;
use App\Modules\Payment\Enums\InvoiceStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use App\Modules\InvoiceGeneration\InvoiceSnapshot as Snapshot;
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
			$total = $total->plus($amount->amount);
		}

		return $total->toScale(2, RoundingMode::HalfUp)->__toString();

	}

	/**
	 * fetchAmountsOfTransactionsByInvoiceId function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return Collection
	 */
	public function fetchAmountsOfTransactionsByInvoiceId(int $company_id, int $invoice_id) : Collection {

		return Transaction::select('amount')->where([['invoice_id', '=', $invoice_id], ['company_id', '=', $company_id], ['status', '<>', TransactionStatus::VOID->value], ['status', '<>', TransactionStatus::PENDING->value]])->get();

	}

	private function updateInvoiceStatusForPayments(Transaction $transaction, bool $notify = true) : bool {
		
		$invoice = $this->fetchInvoiceById($transaction->invoice_id);

		$total = BigDecimal::of($invoice->total);

		$paid_amount = $this->sumOfAmounts($this->fetchAmountsOfTransactionsByInvoiceId($transaction->company_id, $transaction->invoice_id));

		$paid_amount = BigDecimal::of($paid_amount);

		$balance_due = $total->minus($paid_amount);

		$paid_in_full = $paid_amount->isEqualTo($total);
		$overpaid = $paid_amount->isGreaterThan($total);
		$partially_paid = $paid_amount->isLessThan($total) && $paid_amount->isGreaterThan(BigDecimal::of(0));

		if((int) $invoice->status === (int) InvoiceStatus::CANCELLED->value && $notify){
			app(Notification::class)->notify($invoice->company_id, NotificationType::INVOICE_CANCELLED_PAID, 'Your customer paid cancelled invoice', 'Invoice: '. $invoice->invoice_number.' was cancelled and customer made a payment towards it. Transaction id: '.$transaction->id.', Identifer: '.$transaction->token_id_identifier, []);
		}

		if($overpaid && $notify){
			app(Notification::class)->notify($invoice->company_id, NotificationType::INVOICE_OVERPAID, 'Your customer overpaid invoice', 'Invoice: '. $invoice->invoice_number.' was overpaid by your customer. Transaction id: '.$transaction->id.', Identifer: '.$transaction->token_id_identifier, []);
		}

		$status_indicator = InvoiceStatus::PENDING->value;

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