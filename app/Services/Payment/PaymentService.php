<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Repositories\Payment\PaymentRepository;

class PaymentService{

	public function __construct(
		private PaymentRepository $payment_repository
	){}

	/**
	 * ifInvoiceIsPaid function
	 *
	 * @param string $uuid
	 * @return boolean
	 */
	public function ifInvoiceIsPaid(string $uuid) : bool {
		return $this->payment_repository->ifInvoiceIsPaid($uuid);
	}
	
	/**
	 * fetchNotPaidInvoiceByUuid function
	 *
	 * @param string $uuid
	 * @return Invoice|null
	 */
	public function fetchInvoiceByUuid(string $uuid) : ?Invoice {
		return $this->payment_repository->fetchInvoiceByUuid($uuid);
	}

}