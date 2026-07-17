<?php

namespace App\Modules\Payment\Controllers;

use App\Helpers\General;
use App\Http\Controllers\Controller;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Payment;
use Exception;
use Illuminate\Http\Request;

class PayPalController extends Controller{

	public function __construct(private DatabaseOperations $database_operations){}
	/*
		check tomorrow for to make sure it marks invoice as PAID.
	*/
	public function handlePaymentWebhook(Request $request){

		$data = $request->all();
		
		try{
			$settings = $this->database_operations->fetchPayPalSettings($data);
			
			$webhook_data = $settings['webhook_data'];
			
			$payment = new Payment(new PayPal(
												$webhook_data['company_id'], 
												$webhook_data['invoice_id'], 
												$settings['settings']['client_id'], 
												$settings['settings']['app_id'], 
												decrypt($settings['settings']['secret']), 
												$settings['settings']['mode'], 
												$webhook_data['currency_code'],
												$webhook_data['balance_due'],
											)
									);
			$data['webhook_id'] = $settings['settings']['webhook_id'];
			$data['order_id'] = $settings['order_id'];

			$payment->handlePayment($data, $request);

		}catch(PaymentException $e){
			return response($e->getMessage(), $e->getCode());

		}catch(Exception $e){
			return General::wentWrong();
		}

		
		
	}

}
