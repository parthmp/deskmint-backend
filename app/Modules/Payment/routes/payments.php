<?php

use App\Modules\Payment\Gateways\PayPal\Controllers\PayPalController;
use Illuminate\Support\Facades\Route;

Route::post('paypal/webhook', [PayPalController::class, 'handlePaymentWebhook']);
