<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [LoginController::class, 'login']);
Route::post('/resend-otp', [LoginController::class, 'resendOTP']);
Route::post('/validate-otp', [LoginController::class, 'validateOTP']);