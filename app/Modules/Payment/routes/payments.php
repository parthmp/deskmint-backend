<?php

use App\Modules\Payment\Gateways\PayPal\Controllers\PayPalController;
use Illuminate\Support\Facades\Route;

Route::post('paypal/webhook', [PayPalController::class, 'handlePaymentWebhook']);

Route::get('/payment-cancel', function(){
	return view('payment_info', ['type' => 'fail']);
});

Route::get('/payment-success', function(){
	return view('payment_info', ['type' => 'pass']);
});
