<?php

namespace App\Services\Invoice;

use Illuminate\Http\Request;

class InvoiceSaveService extends InvoiceBaseService{
	
	/**
	 * save function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return integer
	 */
	public function save(Request $request, int $company_id, int $invoice_id = 0) : int {
		return $this->saveOrUpdate($request, $company_id, $invoice_id);
	}

}

