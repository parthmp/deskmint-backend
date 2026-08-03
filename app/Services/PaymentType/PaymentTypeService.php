<?php

namespace App\Services\PaymentType;

use App\Models\PaymentType;
use App\Repositories\PaymentType\PaymentTypeRepository;

/**
 * PaymentTypeService class
 */
class PaymentTypeService {

	public function __construct(
		private PaymentTypeRepository $payment_type_repository
	){}

	/**
	 * create function
	 *
	 * @param string $payment_type
	 * @return PaymentType
	 */
	public function create(string $payment_type) : PaymentType {
		return $this->payment_type_repository->createOrUpdate($payment_type);
	}

}