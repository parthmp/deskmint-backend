<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Gateways\Payment;
use Exception;
use Illuminate\Http\Request;

class StripeController extends Controller{
	
	public function __construct(private DatabaseOperations $database_operations){}

	public function handlePaymentWebhook(Request $request){

		$data = $request->all();
		logger("\n\n\n\n\n\n");
		logger($data['type']);
		logger($data);
		
		// try{

		// 	$data = $request->all();
				
		// 	$settings_data = $this->database_operations->fetchStripeSettings($data);
			
		// 	$payment = new Payment((int) $settings_data['webhook_data']['company_id']);
			
		// 	$payment->stripe()->handleWebhook($request, $settings_data);
			
		// }catch(PaymentException $e){
			
		// 	if($e->getValidity() === 'unsupported_event'){
		// 		return response('ok', 200);
		// 	}

		// 	return response($e->getMessage(), $e->getCode());
			
		// }catch(Exception $e){
		// 	return response('processing failed', 500);
		// }

	}

}