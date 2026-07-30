<?php

namespace App\Repositories\Gateway;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\TransactionStatus;

class GatewayRepository{

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
		return Transaction::select('payment_urls.url as payment_url', 'transactions.amount as amount', 'transactions.payment_gateway as payment_gateway')->join('transaction_references', 'transaction_references.transaction_id', '=', 'transactions.id')->join('payment_urls', 'payment_urls.transaction_id', '=', 'transactions.id')->where([['transactions.created_at', '>', now()->subHours(2)], ['transaction_references.invoice_id', '=',$invoice_id]])->orderBy('transactions.id', 'desc')->first();
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
