<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\FieldTypesController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LoginController;
use App\Http\Middleware\DefaultCompany;
use App\Http\Middleware\IfUserHasAccessToFeature;
use App\Http\Middleware\ValidateDeviceAndTokens;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/**
* Using explicit route definitions instead of Route::resource throughout this application
* 
* Reasons:
* - Index routes require POST method for complex payloads (auth tokens, filtering, pagination)
* - Flexibility: Avoids URL length limits and query string complexity
* - Clarity: Explicit routes are easier to understand than resource()->except() patterns
*/

Route::post('manage-field-types', [FieldTypesController::class, 'index']);
Route::post('manage-field-types/create', [FieldTypesController::class, 'store']);
// ... rest of your routes

Route::middleware(['throttle:60,1'])->group(function (){
	Route::post('login', [LoginController::class, 'login'])->name('login');
	Route::post('send-reset-password-code', [ForgotPasswordController::class, 'sendResetPasswordCode']);
});

Route::middleware(['throttle:10,1'])->group(function (){
	Route::post('resend-otp', [LoginController::class, 'resendOTP']);
	Route::post('validate-otp', [LoginController::class, 'validateOTP']);
	Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);
});

Route::middleware(['throttle:60,1', 'auth:sanctum', ValidateDeviceAndTokens::class])->group(function (){
	Route::post('check-company-exists', [CompanyController::class, 'checkCompanyExists']);
	Route::post('set-default-company', [CompanyController::class, 'setDefaultCompany']);
});

Route::middleware(['throttle:60,1', 'auth:sanctum', ValidateDeviceAndTokens::class, IfUserHasAccessToFeature::class, DefaultCompany::class])->group(function (){

	Route::resource('manage-admins', AdminController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('manage-admins', [AdminController::class, 'destroy']);

	
	/*
	Route::resource('manage-field-types', FieldTypesController::class)->except(array_merge(config('global.skip_routes'), ['index']));
	Route::post('manage-field-types', [FieldTypesController::class, 'index']);
	Route::post('manage-field-types/create', [FieldTypesController::class, 'store']);*/

	Route::get('manage-field-types/fetch-input-types', [FieldTypesController::class, 'getInputTypes']);
	Route::post('manage-field-types', [FieldTypesController::class, 'index']);
	Route::post('manage-field-types/create', [FieldTypesController::class, 'store']);
	Route::get('manage-field-types/{id}', [FieldTypesController::class, 'show']);
	/*Route::patch('manage-field-types/{id}', [FieldTypesController::class, 'update']);
	Route::delete('manage-field-types/{id}', [FieldTypesController::class, 'destroy']);*/

});

