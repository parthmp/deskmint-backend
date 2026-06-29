<?php

namespace App\Modules\Payment\Gateways\PayPal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Modules\Payment\Payment;
use Exception;
use Illuminate\Http\Request;

class StripeController extends Controller{
	
	public function __construct(private DatabaseOperations $database_operations){}

	public function handlePaymentWebhook(Request $request){
		
		$data = $request->all();
		try{

			$settings = $this->database_operations->fetchStripeSettings($data);
			$webhook_data = $settings['webhook_data'];

			$stripe_object = new Stripe($webhook_data['invoice_id'], decrypt($settings['settings']['secret']), $webhook_data['currency_code'], $webhook_data['balance_due']);
			$stripe_object->setWebhookSecret(decrypt($settings['settings']['webhook_secret']));

			$payment = new Payment($stripe_object);

			$data['order_id'] = $settings['order_id'];

			$payment->handlePayment($data, $request);

			return response('ok', 200);

		}catch(PaymentException $e){

			if($e->getValidity() === 'unsupported_event'){
				return response('ok', 200);
			}

			return response('processing failed', 500);
			
		}catch(Exception $e){
			return response('processing failed', 500);
		}

	}

}