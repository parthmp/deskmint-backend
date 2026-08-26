<?php

namespace App\Repositories\PaymentRequest;

use App\Enums\PaymentRequests\PaymentRequestStatus;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Enums\PaymentStatus;
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
	 * fetchByIdWithCompanyId function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @param boolean $with_trahsed
	 * @return PaymentRequest
	 */
	public function fetchByIdWithCompanyId(int $company_id, int $id, $with_trahsed = false) : PaymentRequest {
		$payment_request = PaymentRequest::where([['company_id', '=', $company_id], ['id', '=', $id]]);
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
	 * @return PaymentRequest
	 */
	public function createOrUpdate(array $data, ?int $id = null) : PaymentRequest {

		if($id){
			$payment_request = $this->fetchById($id);
			$payment_request->hidden_sent_at = $data['hidden_sent_at'];;
		}else{
			$payment_request = new PaymentRequest();
			$payment_request->uuid = Str::uuid();
			$payment_request->company_id = $data['company_id'];
			$payment_request->last_reminder_sent_at = null;
			$payment_request->hidden_sent_at = now();
			
		}

		$payment_request->client_id = $data['client_id'];
		$payment_request->currency_id = $data['currency_id'];
		$payment_request->transaction_id = $data['transaction_id'];
		$payment_request->label = $data['label'];
		$payment_request->amount = $data['amount'];
		$payment_request->status = $data['status'];
		$payment_request->payment_gateway = $data['payment_gateway'];
		$payment_request->sent_at = $data['sent_at'];
		$payment_request->save();
		return $payment_request;
	}

	/**
	 * fetchDataForSendingRequest function
	 *
	 * @param integer $company_id
	 * @param integer $payment_request_id
	 * @return array
	 */
	public function fetchDataForSendingRequest(int $company_id, int $payment_request_id) : array {

		$data = PaymentRequest::select('clients.first_name as first_name', 'clients.last_name as last_name', 'currencies.code as currency', 'payment_requests.amount as amount', 'payment_requests.uuid as uuid', 'payment_requests.payment_gateway as payment_gateway', 'clients.email as email')->join('clients', 'clients.id', '=', 'payment_requests.client_id')->join('currencies', 'currencies.id', '=', 'payment_requests.currency_id')
									->where([['payment_requests.company_id', '=', $company_id], ['payment_requests.id', '=', $payment_request_id]])
									->first();
		
		return $data->toArray();

	}

	/**
	 * markSent function
	 *
	 * @param PaymentRequest $payment_request
	 * @return boolean
	 */
	public function markSent(PaymentRequest $payment_request) : bool {

		$payment_request->sent_at = now();
		$payment_request->hidden_sent_at = now();
		$payment_request->status = PaymentRequestStatus::SENT->value;

		return $payment_request->save();

	}

	/**
	 * markCancel function
	 *
	 * @param PaymentRequest $payment_request
	 * @return boolean
	 */
	public function markCancel(PaymentRequest $payment_request) : bool {

		$payment_request->status = PaymentRequestStatus::CANCELLED->value;

		return $payment_request->save();

	}

	/**
	 * fetchForEdit function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return array
	 */
	public function fetchForEdit(int $company_id, int $id) : array {

		return PaymentRequest::select('clients.full_name as full_name', 'cc.code as client_currency', 'cc.id as client_currency_id','pc.code as payment_request_currency', 'pc.id as payment_request_currency_id', 'payment_requests.*', 'clients.id as client_id')
								->join('clients', 'clients.id', '=', 'payment_requests.client_id')
								->join('currencies as pc', 'pc.id', '=', 'payment_requests.currency_id')
								->join('currencies as cc', 'cc.id', '=', 'clients.currency_id')
									->where([['payment_requests.company_id', '=', $company_id], ['payment_requests.id', '=', $id]])
									->first()->toArray();

	}

	/**
	 * markCompleted function
	 *
	 * @param PaymentRequest $pr
	 * @return boolean
	 */
	public function markCompleted(PaymentRequest $pr) : bool {
		$pr->status = PaymentRequestStatus::COMPLETED->value;
		$pr->paid_at = now();
		return $pr->save();
	}

	/**
	 * createPaymentForRequest function
	 *
	 * @param array $data
	 * @return Payment
	 */
	public function createPaymentForRequest(array $data) : Payment {

		$payment = new Payment();
		$payment->company_id = $data['company_id'];
		$payment->client_id = $data['client_id'];
		$payment->transaction_id = null;
		$payment->currency_id = $data['currency_id'];
		$payment->payment_type_id = $data['payment_type_id'];
		$payment->status = PaymentStatus::NOT_APPLIED->value;
		$payment->amount = $data['amount'];
		$payment->applied_amount = 0;
		$payment->amount_left_to_be_applied = $data['amount'];
		$payment->save();

		return $payment;

	}

}