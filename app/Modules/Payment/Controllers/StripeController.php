<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Enums\StripeEvents;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Gateways\Payment;
use Exception;
use Illuminate\Http\Request;

class StripeController extends Controller{
	
	public function __construct(private DatabaseOperations $database_operations){}

	public function handlePaymentWebhook(Request $request){

		$data = $request->all();

		logger("\n\n\n");
		logger("FROM STRIPE");
		logger("===========");
		logger($data);
		logger("===========");
		logger("\n\n\n");
		
		$event_type = $data['type'] ?? null;

		if(!in_array((string) $event_type, StripeEvents::getAll())){
			logger("event type ".$event_type." is not added in the application for stripe");
			return response('ok', 200);
		}
		
		$settings_data = [];

		if(((string) $event_type === StripeEvents::CHECKOUT_COMPLETED->value || (string) $event_type === StripeEvents::CHECKOUT_COMPLETED_ASYNC->value)){
			if($data['data']['object']['mode'] !== 'subscription'){
				$settings_data = $this->database_operations->fetchStripeSettings($data);
			}
		}
		
		if((string) $event_type === StripeEvents::INVOICE_PAID->value){
			
			$fetched_data = $this->database_operations->fetchStripeSettingsForSubscriptions($data);
			if(!$fetched_data['recurring_invoice_data']){
				return response('ok', 200);
			}
			
			$settings_data['webhook_data'] = $fetched_data['recurring_invoice_data']->toArray();
			$settings_data['settings'] = [
				'order_id'					=>	$fetched_data['order_id'],
				'recurring_invoice_id'		=>	$fetched_data['recurring_invoice_id'],
				'subscription_id'			=>	$fetched_data['subscription_id'],
			];

		}

		if(empty($settings_data)){
			return response('ok', 200);
		}
		
		$payment = new Payment((int) $settings_data['webhook_data']['company_id']);
		
		$payment->stripe()->handleWebhook($request, $settings_data);

		return response('ok', 200);

	}

}