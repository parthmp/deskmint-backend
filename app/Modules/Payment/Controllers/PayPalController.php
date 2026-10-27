<?php

namespace App\Modules\Payment\Controllers;

use App\Helpers\General;
use App\Http\Controllers\Controller;
use App\Modules\Payment\DatabaseOperations;
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

		$data = $this->database_operations->fetchPayPalSettings($data);

		$payment = new Payment((int) $data['webhook_data']['company_id']);
		$payment->paypal((string) $data['webhook_data']['currency_code'])->handleWebhook($request);
		
	}

}
