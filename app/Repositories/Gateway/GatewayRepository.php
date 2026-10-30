<?php

namespace App\Repositories\Gateway;

use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceUrl;
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
	 * @param integer $currency_id
	 * @return Transaction|null
	 */
	public function fetchTransactionOfPast(int $invoice_id, int $currency_id) : ?Transaction {
		return Transaction::select('payment_urls.url as payment_url', 'transactions.amount as amount', 'transactions.payment_gateway as payment_gateway')->join('transaction_references', 'transaction_references.transaction_id', '=', 'transactions.id')->join('payment_urls', 'payment_urls.transaction_id', '=', 'transactions.id')->where([['transactions.created_at', '>', now()->subHours(2)], ['transaction_references.invoice_id', '=',$invoice_id], ['transactions.currency_id', '=', $currency_id]])->orderBy('transactions.id', 'desc')->first();
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

	/**
	 * fetchPaymentRequestById function
	 *
	 * @param integer $pr_id
	 * @return PaymentRequest|null
	 */
	public function fetchPaymentRequestById(int $pr_id) : ?PaymentRequest {
		return PaymentRequest::where('id', '=', $pr_id)->first();
	}

	/**
	 * fetchRequestByUuid function
	 *
	 * @param string $uuid
	 * @return PaymentRequest|null
	 */
	public function fetchRequestByUuid(string $uuid) : ?PaymentRequest {
		return PaymentRequest::where('uuid', '=', $uuid)->first();
	}

	/**
	 * fetchTransactionOfPastForRequests function
	 *
	 * @param integer $pr_id
	 * @param integer $currency_id
	 * @return Transaction|null
	 */
	public function fetchTransactionOfPastForRequests(int $pr_id, int $currency_id) : ?Transaction {
		return Transaction::select('payment_urls.url as payment_url', 'transactions.amount as amount', 'transactions.payment_gateway as payment_gateway')->join('transaction_references', 'transaction_references.transaction_id', '=', 'transactions.id')->join('payment_urls', 'payment_urls.transaction_id', '=', 'transactions.id')->where([['transactions.created_at', '>', now()->subHours(2)], ['transaction_references.payment_request_id', '=',$pr_id], ['transactions.currency_id', '=', $currency_id]])->orderBy('transactions.id', 'desc')->first();
	}
	
	/**
	 * fetchRecurringInvoiceByUuid function
	 *
	 * @param string $uuid
	 * @return RecurringInvoice|null
	 */
	public function fetchRecurringInvoiceByUuid(string $uuid, array $selects = ['*']) : ?RecurringInvoice {
		return RecurringInvoice::select(...$selects)->join('currencies', 'currencies.id', 'recurring_invoices.currency_id')->join('clients', 'clients.id', '=', 'recurring_invoices.client_id')->where('recurring_invoices.uuid', '=', $uuid)->first();
	}

	/**
	 * fetchCachedUrlForRecurringInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @param integer $payment_gateway
	 * @param string $total
	 * @param integer $currency_id
	 * @return string|null
	 */
	public function fetchCachedUrlForRecurringInvoice(int $company_id, int $recurring_invoice_id, int $payment_gateway, string $total, int $currency_id) : ?string {

		$record = RecurringInvoiceUrl::where([['company_id', '=', $company_id], ['recurring_invoice_id', '=', $recurring_invoice_id], ['payment_gateway', '=', $payment_gateway], ['total', '=', $total], ['currency_id', '=', $currency_id], ['created_at', '>', now()->subHours(2)]])->orderBy('id', 'desc')->first();

		if(!$record){
			return null;
		}

		return $record->url;

	}

}
