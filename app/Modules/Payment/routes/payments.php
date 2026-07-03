<?php

use App\Modules\Payment\Controllers\PayPalController;
use App\Modules\Payment\Controllers\StripeController;
use Illuminate\Support\Facades\Route;
//https://xxx.dev/payments/paypal/webhook
Route::post('paypal/webhook', [PayPalController::class, 'handlePaymentWebhook']);
//https://xxx.dev/payments/stripe/webhook
Route::post('stripe/webhook', [StripeController::class, 'handlePaymentWebhook']);

Route::get('/payment-cancel', function(){
	return view('payment_info', ['type' => 'fail']);
});

Route::get('/payment-success', function(){
	return view('payment_info', ['type' => 'pass']);
});


