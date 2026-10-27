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
		logger($data);

		$event_type = $data['event_type'] ?? null;

		if((string) $event_type === PayPalEvents::SALE_COMPLETED->value || (string) $event_type === PayPalEvents::SUBSCRIPTION_ACTIVATED->value){
			$sub_data = $this->database_operations->fetchPayPalSettingsForSubscriptions($data);
			$settings_data['webhook_data']['company_id'] = $sub_data['company_id'];
			$settings_data['webhook_data']['currency_code'] = $sub_data['code'];
			
		}else{
			$settings_data = $this->database_operations->fetchPayPalSettings($data);
		}

		$payment = new Payment((int) $settings_data['webhook_data']['company_id']);
		$payment->paypal((string) $settings_data['webhook_data']['currency_code'])->handleWebhook($request, $settings_data);
		
	}

}
