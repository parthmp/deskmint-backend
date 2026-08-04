<?php

namespace App\Services\PaymentRequest;

use App\Enums\PaymentRequests\PaymentRequestStatus;
use App\Repositories\PaymentRequest\PaymentRequestRepository;

/**
 * PaymentRequestService class
 */
class PaymentRequestService {

	public function __construct(
		private PaymentRequestRepository $payment_request_repository
	){}

	/**
	 * fetchInit function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchInit(int $company_id) : array {
		return $this->payment_request_repository->fetchInit($company_id);
	}

	/**
	 * create function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function create(array $data) : bool {

		$pass_data = [];

		$pass_data['company_id'] = (int) $data['company_id'];
		$pass_data['client_id'] = (int) $data['client_id'];
		$pass_data['transaction_id'] = null;
		$pass_data['label'] = (string) $data['label'];
		$pass_data['amount'] = (string) $data['amount'];
		$pass_data['status'] = ((bool) $data['send_request']) ? PaymentRequestStatus::SENT->value : PaymentRequestStatus::DRAFT->value;
		$pass_data['payment_gateway'] = (int) $data['payment_gateway'];
		$pass_data['send_reminders'] = ((bool) $data['send_reminders']) ? 1 : 0;
		$pass_data['reminders_sent'] = 0;

		return $this->payment_request_repository->createOrUpdate($pass_data);
	}

}