<?php

namespace App\Repositories\Transaction;

use App\Helpers\General;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\TransactionGatewayDetail;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * TransactionRepository class
 */
class TransactionRepository {

	/**
	 * fetchTransactionView function
	 *
	 * @param integer $transaction_id
	 * @return array
	 */
	public function fetchTransactionView(int $transaction_id, int $company_id) : array {

		$transaction = Transaction::select(
			'transactions.*',
			'currencies.code as currency_code',
			'invoices.invoice_number as invoice_number',
			'users.name as voided_by_name',
		)->where([['transactions.id', '=', $transaction_id], ['transactions.company_id', '=', $company_id]])->join('invoices', 'invoices.id', '=', 'transactions.invoice_id')->join('currencies', 'currencies.id', '=', 'invoices.currency_id')->leftjoin('users', 'users.id', '=', 'transactions.voided_by')->first();

		if(!$transaction){
			return [];
		}

		$transaction = $transaction->toArray();

		$transaction['payment_method'] = General::getPaymentMethodName((int) $transaction['payment_method']);
		$transaction['status'] = TransactionStatus::getTransactionStatusLabel((int) $transaction['status']);
		$transaction['is_approved'] = ((int) $transaction['is_approved'] === 1) ? 'Yes' : 'No';
		$transaction['is_payment_captured'] = ((int) $transaction['is_payment_captured'] === 1) ? 'Yes' : 'No';
		$transaction['is_echeck'] = ((int) $transaction['is_echeck'] === 1) ? 'Yes' : 'No';

		return $transaction;

	}

	/**
	 * fetchTransactionStatus function
	 *
	 * @param integer $company_id
	 * @param integer $transaction_id
	 * @return Transaction
	 */
	public function fetchTransaction(int $company_id, int $transaction_id) : Transaction {
		return Transaction::where([['transactions.id', '=', $transaction_id], ['transactions.company_id', '=', $company_id]])->first();
	}
	
}