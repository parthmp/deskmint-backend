<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\ClientsCustomFieldsController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySettingsAdditionalFieldsController;
use App\Http\Controllers\CompanySettingsAddressController;
use App\Http\Controllers\CompanySettingsDefaultsController;
use App\Http\Controllers\CompanySettingsDetailsController;
use App\Http\Controllers\CompanySettingsLogoController;
use App\Http\Controllers\CountriesController;
use App\Http\Controllers\CurrenciesControlller;
use App\Http\Controllers\FieldTypesController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\IndustriesController;
use App\Http\Controllers\InvoicesCustomFieldsController;
use App\Http\Controllers\InvoiceSettingsClientDetailsController;
use App\Http\Controllers\InvoiceSettingsGeneralController;
use App\Http\Controllers\InvoiceSettingsNumbersController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProductsController;
use App\Http\Middleware\DefaultCompany;
use App\Http\Middleware\IfUserHasAccessToFeature;
use App\Http\Middleware\ValidateDeviceAndTokens;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1'])->group(function () {
	Route::post('login', [LoginController::class, 'login'])->name('login');
	Route::post('send-reset-password-code', [ForgotPasswordController::class, 'sendResetPasswordCode']);
});

Route::middleware(['throttle:10,1'])->group(function () {
	Route::post('resend-otp', [LoginController::class, 'resendOTP']);
	Route::post('validate-otp', [LoginController::class, 'validateOTP']);
	Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);
});

Route::middleware(['throttle:60,1', 'auth:sanctum', ValidateDeviceAndTokens::class])->group(function () {
	Route::post('check-company-exists', [CompanyController::class, 'checkCompanyExists']);
	Route::post('set-default-company', [CompanyController::class, 'setDefaultCompany']);
});

Route::middleware(['throttle:600,1', 'auth:sanctum', ValidateDeviceAndTokens::class, IfUserHasAccessToFeature::class, DefaultCompany::class])->group(function () {

	Route::resource('manage-admins', AdminController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('manage-admins', [AdminController::class, 'destroy']);


	Route::get('manage-field-types/fetch-input-types', [FieldTypesController::class, 'getInputTypes']);
	Route::resource('manage-field-types', FieldTypesController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('manage-field-types', [FieldTypesController::class, 'destroy']);


	Route::get('clients-custom-fields/fetch-field-types', [ClientsCustomFieldsController::class, 'fetchFieldTypes']);
	Route::resource('clients-custom-fields', ClientsCustomFieldsController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('clients-custom-fields', [ClientsCustomFieldsController::class, 'destroy']);

	Route::get('get-countries', [CountriesController::class, 'fetchCountries']);
	Route::get('get-currencies', [CurrenciesControlller::class, 'fetchCurrencies']);
	Route::get('get-industries', [IndustriesController::class, 'fetchIndustries']);

	Route::get('manage-clients/fetch-clients-custom-fields', [ClientsController::class, 'fetchClientsCustomFields']);
	Route::get('manage-clients/fetch-arranged-columns', [ClientsController::class, 'fetchArrangedColumns']);
	Route::post('manage-clients/save-arranged-columns', [ClientsController::class, 'saveArrangedColumns']);
	Route::resource('manage-clients', ClientsController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('manage-clients', [ClientsController::class, 'destroy']);

	Route::resource('manage-products', ProductsController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('manage-products', [ProductsController::class, 'destroy']);

	Route::get('invoices-custom-fields/fetch-field-types', [InvoicesCustomFieldsController::class, 'fetchFieldTypes']);
	Route::resource('invoices-custom-fields', InvoicesCustomFieldsController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('invoices-custom-fields', [InvoicesCustomFieldsController::class, 'destroy']);


	/* settings */
	Route::get('manage-invoice-settings', [InvoiceSettingsGeneralController::class, 'show']);
	Route::post('manage-invoice-settings', [InvoiceSettingsGeneralController::class, 'saveOrUpdate']);

	Route::get('manage-invoice-settings-numbers', [InvoiceSettingsNumbersController::class, 'show']);
	Route::post('manage-invoice-settings-numbers', [InvoiceSettingsNumbersController::class, 'saveOrUpdate']);

	Route::get('manage-invoice-settings-client-details', [InvoiceSettingsClientDetailsController::class, 'show']);
	Route::post('manage-invoice-settings-client-details', [InvoiceSettingsClientDetailsController::class, 'saveOrUpdate']);

	Route::get('manage-company-settings-details', [CompanySettingsDetailsController::class, 'show']);
	Route::post('manage-company-settings-details', [CompanySettingsDetailsController::class, 'saveOrUpdate']);

	Route::get('manage-company-settings-address', [CompanySettingsAddressController::class, 'show']);
	Route::post('manage-company-settings-address', [CompanySettingsAddressController::class, 'saveOrUpdate']);

	Route::get('manage-company-settings-logo', [CompanySettingsLogoController::class, 'show']);
	Route::post('manage-company-settings-logo', [CompanySettingsLogoController::class, 'saveOrUpdate']);
	Route::delete('manage-company-settings-logo', [CompanySettingsLogoController::class, 'destroy']);

	Route::get('manage-company-settings-defaults', [CompanySettingsDefaultsController::class, 'show']);
	Route::post('manage-company-settings-defaults', [CompanySettingsDefaultsController::class, 'saveOrUpdate']);

	//Route::get('manage-company-settings-defaults', [CompanySettingsDefaultsController::class, 'show']);
	Route::post('manage-company-settings-additional-fields', [CompanySettingsAdditionalFieldsController::class, 'saveOrUpdate']);

});
