<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\PaymentSettingsPayPal\CreatePaymentSettingsPayPalRequest;
use App\Services\PaymentSettingsPaypal\PaymentSettingsPaypalService;
use Exception;
use Illuminate\Http\Request;

class PaymentSettingsPaypalController extends Controller{

	public function __construct(private PaymentSettingsPaypalService $payment_settings_paypal_service){}

	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		return $this->payment_settings_paypal_service->fetch($company_id);;

	}
    
	public function upsert(CreatePaymentSettingsPayPalRequest $request){
		
		$data = $request->validated();

		try{
			
			if($this->payment_settings_paypal_service->update($data)){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function destroy(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{

			$this->payment_settings_paypal_service->destroy($company_id);
			return response(['message' => 'Removed successfully', 'validity' => 'removed_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

		

	}

}
