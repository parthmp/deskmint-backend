<?php

namespace App\Modules\Payment\Gateways\PayPal\Implementation;

use App\Models\TransactionReference as TransactionReferenceModel;
use App\Modules\Payment\DatabaseOperations;
use Srmklive\PayPal\Services\PayPal as Provider;

class TransactionReference {

	public function __construct(
		private int $company_id,
		private int $user_id,
		private int $client_id,
		private ?int $invoice_id,
		private ?int $pr_id,
		private ?int $transaction_id = null,
		private ?int $id = null
	){}

	/**
	 * getDB function
	 *
	 * @return DatabaseOperations
	 */
	private function getDB() : DatabaseOperations {
		return app(DatabaseOperations::class);
	}

	/**
	 * create function
	 *
	 * @return TransactionReferenceModel
	 */
	public function create() : TransactionReferenceModel {
		$db = $this->getDB();
		return $db->upsertTransactionReference((int) $this->company_id, (int) $this->user_id, $this->invoice_id, $this->pr_id);
	}

	public function upsert(){
		
	}


	
}