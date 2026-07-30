<?php

namespace App\Repositories\Transaction;


use App\Models\Transaction;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Enums\TransactionStatus;

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
		)->where([['transactions.id', '=', $transaction_id], ['transactions.company_id', '=', $company_id]])->join('currencies', 'currencies.id', '=', 'transactions.currency_id')->first();

		if(!$transaction){
			return [];
		}

		$transaction = $transaction->toArray();

		$transaction['payment_gateway'] = PaymentGateway::getLabelByValue((int) $transaction['payment_gateway']);
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