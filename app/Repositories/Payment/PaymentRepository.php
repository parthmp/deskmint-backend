<?php

namespace App\Repositories\Payment;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\TransactionStatus;

class PaymentRepository{

	/**
	 * ifInvoiceIsPaid function
	 *
	 * @param string $uuid
	 * @return boolean
	 */
	public function ifInvoiceIsPaid(string $uuid) : bool {
		
		$invoice = Invoice::where([['uuid', '=', $uuid], ['status', '=', (int) InvoiceStatus::PAID->value]])->first();

		if(!$invoice){
			return false;
		}

		return true;

	}

	/**
	 * fetchInvoiceByUuid function
	 *
	 * @param string $uuid
	 * @return Invoice|null
	 */
	public function fetchInvoiceByUuid(string $uuid) : ?Invoice {
		return Invoice::where('uuid', '=', $uuid)->with('currency')->first();
	}

	/**
	 * fetchTransactionOfPast function
	 *
	 * @param integer $invoice_id
	 * @return Transaction|null
	 */
	public function fetchTransactionOfPast(int $invoice_id) : ?Transaction {
		return Transaction::where([['invoice_id', '=', $invoice_id], ['created_at', '>', now()->subHours(2)], ['status', '<>', TransactionStatus::VOID->value]])->with('payment_url')->orderBy('id', 'desc')->first();
	}

	/**
	 * fetchInvoiceById function
	 *
	 * @param integer $invoice_id
	 * @return Invoice|null
	 */
	public function fetchInvoiceById(int $invoice_id) : ?Invoice {
		return Invoice::where('id', '=', $invoice_id)->first();
	}

}
