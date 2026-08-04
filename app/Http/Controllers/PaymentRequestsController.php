<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\PaymentRequest\CreateEditPaymentRequestRequest;
use App\Services\PaymentRequest\PaymentRequestService;
use Exception;
use Illuminate\Http\Request;

class PaymentRequestsController extends Controller {

	public function __construct(
		private PaymentRequestService $payment_request_service
	){}


	public function fetchInit(GenericRequest $request){
		$data = $request->validated();
		return $this->payment_request_service->fetchInit((int) $data['company_id']);
	}

	public function store(CreateEditPaymentRequestRequest $request){

		$data = $request->validated();
		
		try{

			$this->payment_request_service->create($data);
			return response(['message' => 'Payment request created successfully', 'validity' => 'payment_request_created'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}
	

}
