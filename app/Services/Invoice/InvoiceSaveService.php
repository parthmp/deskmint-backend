<?php

namespace App\Services\Invoice;

use Illuminate\Http\Request;

class InvoiceSaveService extends InvoiceBaseService{
	
	/**
	 * save function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function save(Request $request, int $company_id) : void {
		$this->saveOrUpdate($request, $company_id);
	}

}

