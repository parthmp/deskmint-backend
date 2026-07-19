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
	 * markInvoicePaidnDeduct function
	 *
	 * @param Transaction $transaction
	 * @param float $amount
	 * @return boolean
	 */
	private function markInvoicePaidnDeduct(Transaction $transaction, float $amount, bool $notify = true) : bool {
		
		$invoice = $this->fetchInvoiceById($transaction->invoice_id);

		$amount_paid = BigDecimal::of($amount);
		$amount_balance_due = BigDecimal::of($invoice->balance_due);

		$left = $amount_balance_due->minus($amount_paid);

		$paid_in_full = ($amount_paid->isEqualTo($amount_balance_due) || $amount_paid->isGreaterThan($amount_balance_due)) ? 1 : 0;

		$left = $left->toScale(2, RoundingMode::HalfUp)->__toString();

		//check if invoice was cancelled.
		if((int) $invoice->status === (int) InvoiceStatus::CANCELLED->value && $notify){
			app(Notification::class)->notify($invoice->company_id, NotificationType::INVOICE_CANCELLED_PAID, 'Your customer paid cancelled invoice', 'Invoice: '. $invoice->invoice_number.' was cancelled and customer made a payment towards it. Transaction id: '.$transaction->id.', Identifer: '.$transaction->token_id_identifier, []);
		}

		if($amount_paid->isGreaterThan($amount_balance_due) && $notify){
			app(Notification::class)->notify($invoice->company_id, NotificationType::INVOICE_OVERPAID, 'Your customer overpaid invoice', 'Invoice: '. $invoice->invoice_number.' was overpaid by your customer. Transaction id: '.$transaction->id.', Identifer: '.$transaction->token_id_identifier, []);
		}

		$invoice->status = ((int) $paid_in_full === 1) ? (int) InvoiceStatus::PAID->value : (int) InvoiceStatus::PARTIALLY_PAID->value;
		$invoice->balance_due = $left;

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