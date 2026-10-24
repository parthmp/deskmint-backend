<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Modules\RecurringInvoice\DB\DB;

class UpdateLastInvoiceGenerated {
	
	public function __construct(
		private DB $db
	){}


	/**
	 * updateLastInvoiceCreatedAt function
	 *
	 * @param integer $company_id
	 * @param array $inserted
	 * @return void
	 */
	public function updateLastInvoiceCreatedAt(int $company_id, array $inserted) : void {

		$recurring_invoice_ids = array_column($inserted, 'recurring_invoice_id');
		$this->db->updateLastInvoiceCreatedAt($company_id, $recurring_invoice_ids);

	}

}