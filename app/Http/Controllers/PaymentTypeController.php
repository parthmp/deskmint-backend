<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentTypeException;
use App\Helpers\General;
use App\Http\Requests\PaymentType\PaymentTypeCreateEditRequest;
use App\Services\PaymentType\PaymentTypeService;
use Exception;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller {

	public function __construct(
		private PaymentTypeService $payment_type_service
	){}

	public function store(PaymentTypeCreateEditRequest $request){

		$data = $request->validated();

		try{

			$this->payment_type_service->create((string) $data['payment_type']);
			return response(['message' => 'Payment type created successfully', 'validity', 'payment_type_created'], 200);

		}catch(PaymentTypeException $e){

			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());

		}catch(Exception $e){

			return General::wentWrong();
			
		}

	}
    
}
