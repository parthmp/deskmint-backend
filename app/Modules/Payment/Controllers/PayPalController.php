<?php

namespace App\Modules\Payment\Controllers;

use App\Helpers\General;
use App\Http\Controllers\Controller;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Enums\PayPalEvents;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Gateways\Payment;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use Exception;
use Illuminate\Http\Request;

class PayPalController extends Controller{

	public function __construct(
		private DatabaseOperations $database_operations
	){}
	


	public function handlePaymentWebhook(Request $request){

		$data = $request->all();
		
		$event_type = $data['event_type'] ?? null;

		if(!in_array((string) $event_type, PayPalEvents::getAll())){
			logger("event type ".$event_type." is not added in the application");
			return response('ok', 200);
		}

		$sub_events = [
			PayPalEvents::SALE_COMPLETED->value,
			PayPalEvents::SUBSCRIPTION_ACTIVATED->value,
		];

		if(in_array((string) $event_type, $sub_events)){
			
			$data = $this->database_operations->fetchPayPalSettingsForSubscriptions($data);
			$settings_data['webhook_data'] = $data['recurring_invoice_data'];
			$settings_data['settings'] = $data['settings'];
			
		}else{
			$settings_data = $this->database_operations->fetchPayPalSettings($data);
		}

		$payment = new Payment((int) $settings_data['webhook_data']['company_id']);
		$payment->paypal((string) $settings_data['webhook_data']['currency_code'])->handleWebhook($request, $settings_data);

		return response('ok', 200);

		
	}

}
