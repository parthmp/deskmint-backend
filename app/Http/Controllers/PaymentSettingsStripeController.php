<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\PaymentSettingsStripe\CreatePaymentSettingsStripeRequest;
use App\Models\SettingsSection;
use App\Services\PaymentSettingsStripe\PaymentSettingsStripeService;
use Exception;

class PaymentSettingsStripeController extends Controller{
	
	public function __construct(private PaymentSettingsStripeService $payment_settings_stripe_service){}

	public function show(GenericRequest $request){

		$data = $request->validated();
		return $this->payment_settings_stripe_service->fetch($data['company_id']);

	}

	public function upsert(CreatePaymentSettingsStripeRequest $request){

		$data = $request->validated();

		try{
			
			if($this->payment_settings_stripe_service->update($data)){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function destroy(GenericRequest $request){

		$data = $request->validated();
		
		try{

			$this->payment_settings_stripe_service->destroy($data['company_id']);
			return response(['message' => 'Removed successfully', 'validity' => 'removed_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}
    

}
