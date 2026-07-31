<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\Credits\CreditCreateRequest;
use App\Services\Credit\CreditService;
use Exception;
use Illuminate\Http\Request;

class CreditsController extends Controller {

	public function __construct(
		private CreditService $credit_service
	){}

	public function store(CreditCreateRequest $request){

		$data = $request->validated();

		try{

			$this->credit_service->create((int) $data['company_id'], (int) $data['client_id'], (string) $data['amount']);

			return response(['message' => 'Credit created successfully', 'validity' => 'credit_created'], 200);

		}catch(Exception $e){

			return General::wentWrong();

		}
		

	}

}
