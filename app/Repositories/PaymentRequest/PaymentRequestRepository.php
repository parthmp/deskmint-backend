<?php

namespace App\Repositories\PaymentRequest;

use App\Models\PaymentRequest;
use App\Modules\Payment\Enums\PaymentGateway;
use \Illuminate\Support\Str;

/**
 * PaymentRequestRepository class
 */
class PaymentRequestRepository {

	/**
	 * fetchInit function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchInit(int $company_id) : array {
		return PaymentGateway::configuredOptions($company_id);
	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @param boolean $with_trahsed
	 * @return PaymentRequest
	 */
	public function fetchById(int $id, $with_trahsed = false) : PaymentRequest {
		$payment_request = PaymentRequest::where('id', '=', $id);
		if($with_trahsed){
			$payment_request = $payment_request->withTrashed();
		}
		return $payment_request->first();
	}

	/**
	 * createOrUpdate function
	 *
	 * @param array $data
	 * @param integer|null $id
	 * @return boolean
	 */
	public function createOrUpdate(array $data, ?int $id = null) : bool {

		if($id){
			$payment_request = $this->fetchById($id);
		}else{
			$payment_request = new PaymentRequest();
			$payment_request->uuid = Str::uuid();
			$payment_request->company_id = $data['company_id'];
			$payment_request->last_reminder_sent_at = now();
		}

		$payment_request->client_id = $data['client_id'];
		$payment_request->transaction_id = $data['transaction_id'];
		$payment_request->label = $data['label'];
		$payment_request->amount = $data['amount'];
		$payment_request->status = $data['status'];
		$payment_request->payment_gateway = $data['payment_gateway'];
		$payment_request->send_reminders = $data['send_reminders'];
		return $payment_request->save();
	}

}