<?php

namespace App\Repositories\Payment;

use App\Models\Invoice;

class PaymentRepository{

	/**
	 * ifInvoiceIsPaid function
	 *
	 * @param string $uuid
	 * @return boolean
	 */
	public function ifInvoiceIsPaid(string $uuid) : bool {
		
		$invoice = Invoice::where([['uuid', '=', $uuid], ['is_paid', '=', 1]])->first();

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

}
