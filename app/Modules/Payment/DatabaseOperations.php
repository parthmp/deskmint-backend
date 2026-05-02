<?php

namespace App\Modules\Payment;

use App\Models\Transaction;

class DatabaseOperations{
	
	/**
	 * createEmptyTransaction function
	 *
	 * @return Transaction
	 */
	public function createEmptyTransaction() : Transaction{
		return new Transaction();
	}

	/**
	 * insertTransaction function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function insertTransaction(array $data) : bool {
		$transaction = $this->createEmptyTransaction();
		$transaction->invoice_id = $data['invoice_id'];
		$transaction->amount = $data['amount'];
		$transaction->payment_method = $data['payment_method'];
		$transaction->mode = $data['mode'];
		$transaction->token_id_identifier = $data['token_id_identifier'];
		$transaction->additional_details = $data['additional_details'];
		$transaction->is_success = $data['is_success'];
		return $transaction->save();
	}

}