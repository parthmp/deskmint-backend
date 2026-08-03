<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentTypeException;
use App\Helpers\General;
use App\Http\Requests\PaymentType\PaymentTypeCreateEditRequest;
use App\Models\PaymentType;
use App\Modules\DataTable\Requests\DataTableRequest;
use App\Services\DeleteService;
use App\Services\PaymentType\PaymentTypeService;
use Exception;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller {

	public function __construct(
		private PaymentTypeService $payment_type_service,
		private DeleteService $delete_service
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

	
	public function index(DataTableRequest $request){
		$data = $request->validated();
		return $this->payment_type_service->fetch($data);
	}


	public function destroy(Request $request){
		
		try{

			$response = $this->delete_service->deleteByIds($request, PaymentType::class, 'Product');
			return response($response[0], $response[1]);

		}catch(Exception $e){

			return General::wentWrong();

		}

	}
    
}
