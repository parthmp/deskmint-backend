<?php

namespace App\Repositories\PaymentType;

use App\Exceptions\PaymentTypeException;
use App\Models\Payment;
use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Collection;

/**
 * PaymentTypeRepository class
 */
class PaymentTypeRepository {

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return PaymentType|null
	 */
	public function fetchById(int $id) : ?PaymentType {
		return PaymentType::where('id', '=', $id)->first();
	}

	/**
	 * createOrUpdate function
	 *
	 * @param string $payment_type
	 * @param integer|null $id
	 * @return PaymentType
	 */
	public function createOrUpdate(string $payment_type, ?int $id = null) : PaymentType {

		if(!$id){
			$pt_object = new PaymentType();
		}else{
			$pt_object = $this->fetchById($id);
		}

		if(!$pt_object){
			throw new PaymentTypeException('Invalid payment type provided', 'invalid_id', (int) config('global.error_code'));
		}

		$pt_object->payment_type = $payment_type;
		$pt_object->save();

		return $pt_object;

	}

	/**
	 * fetchAllPaymentTypes function
	 *
	 * @return Collection
	 */
	public function fetchAllPaymentTypes() : Collection {
		return PaymentType::select('id', 'payment_type')->orderBy('payment_type', 'asc')->get();
	}

}