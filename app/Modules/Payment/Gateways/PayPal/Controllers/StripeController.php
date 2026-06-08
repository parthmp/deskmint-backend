<?php

namespace App\Modules\Payment\Gateways\PayPal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\DatabaseOperations;

use Illuminate\Http\Request;

class StripeController extends Controller{
	//https://marine-chubby-venus.ngrok-free.dev/payments/stripe/webhook
	public function __construct(private DatabaseOperations $database_operations){}

	public function handlePaymentWebhook(Request $request){
		logger($request->all());
		return 'stripe web hook';
	}

}