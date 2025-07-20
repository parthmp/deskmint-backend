<?php

use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1'])->group(function (){
	Route::post('/login', [LoginController::class, 'login']);
	Route::post('/send-reset-password-code', [ForgotPasswordController::class, 'sendResetPasswordCode']);
});

Route::middleware(['throttle:10,1'])->group(function (){
	Route::post('/resend-otp', [LoginController::class, 'resendOTP']);
	Route::post('/validate-otp', [LoginController::class, 'validateOTP']);
	Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
});