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
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\CurrenciesControlller;
use App\Http\Controllers\EmailSettingsContentController;
use App\Http\Controllers\EmailSettingsRemindersController;
use App\Http\Controllers\EmailSettingsSMTPController;
use App\Http\Controllers\FieldTypesController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\IndustriesController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicesCustomFieldsController;
use App\Http\Controllers\InvoiceSettingsAPFController;
use App\Http\Controllers\InvoiceSettingsClientDetailsController;
use App\Http\Controllers\InvoiceSettingsCompanyAddressController;
use App\Http\Controllers\InvoiceSettingsCompanyDetailsController;
use App\Http\Controllers\InvoiceSettingsGeneralController;
use App\Http\Controllers\InvoiceSettingsInvoiceDetailsController;
use App\Http\Controllers\InvoiceSettingsNumbersController;
use App\Http\Controllers\InvoiceSettingsProductColumnsController;
use App\Http\Controllers\InvoiceSettingsTotalFieldsController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LoginSettingsController;
use App\Http\Controllers\PaymentRequestsController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PaymentSettingsPaypalController;
use App\Http\Controllers\PaymentSettingsStripeController;
use App\Http\Controllers\ProductsController;
use App\Http\Middleware\DefaultCompany;
use App\Http\Middleware\IfUserHasAccessToFeature;
use App\Http\Middleware\ValidateDeviceAndTokens;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentSettingsController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\TransactionsController;
use App\Models\Transaction;

Route::middleware(['throttle:60,1'])->group(function () {
	Route::post('login', [LoginController::class, 'login'])->name('login');
	Route::post('send-reset-password-code', [ForgotPasswordController::class, 'sendResetPasswordCode']);
});

Route::middleware(['throttle:20,1'])->group(function () {
	Route::post('resend-otp', [LoginController::class, 'resendOTP']);
	Route::post('validate-otp', [LoginController::class, 'validateOTP']);
	Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);

	Route::get('invoice/download', [InvoiceController::class, 'servePDF'])->name('invoice.download')->middleware('signed');
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
	//Route::get('manage-clients/invoices/{id}', [ClientsController::class, 'fetchClientInvoices']);

	Route::resource('manage-products', ProductsController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('manage-products', [ProductsController::class, 'destroy']);

	Route::get('invoices-custom-fields/fetch-field-types', [InvoicesCustomFieldsController::class, 'fetchFieldTypes']);
	Route::resource('invoices-custom-fields', InvoicesCustomFieldsController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('invoices-custom-fields', [InvoicesCustomFieldsController::class, 'destroy']);


	/* settings */
	Route::get('manage-invoice-settings', [InvoiceSettingsGeneralController::class, 'show']);
	Route::post('manage-invoice-settings', [InvoiceSettingsGeneralController::class, 'upsert']);

	Route::get('manage-invoice-settings-numbers', [InvoiceSettingsNumbersController::class, 'show']);
	Route::post('manage-invoice-settings-numbers', [InvoiceSettingsNumbersController::class, 'upsert']);
	Route::get('manage-invoice-settings-numbers/reset-number', [InvoiceSettingsNumbersController::class, 'resetInvoiceNumber']);

	Route::get('manage-invoice-settings-client-details', [InvoiceSettingsClientDetailsController::class, 'show']);
	Route::post('manage-invoice-settings-client-details', [InvoiceSettingsClientDetailsController::class, 'saveOrUpdate']);

	Route::get('manage-company-settings-details', [CompanySettingsDetailsController::class, 'show']);
	Route::post('manage-company-settings-details', [CompanySettingsDetailsController::class, 'upsert']);

	Route::get('manage-company-settings-address', [CompanySettingsAddressController::class, 'show']);
	Route::post('manage-company-settings-address', [CompanySettingsAddressController::class, 'upsert']);

	Route::get('manage-company-settings-logo', [CompanySettingsLogoController::class, 'show']);
	Route::post('manage-company-settings-logo', [CompanySettingsLogoController::class, 'upsert']);
	Route::delete('manage-company-settings-logo', [CompanySettingsLogoController::class, 'destroy']);

	Route::get('manage-company-settings-defaults', [CompanySettingsDefaultsController::class, 'show']);
	Route::post('manage-company-settings-defaults', [CompanySettingsDefaultsController::class, 'upsert']);

	Route::get('manage-company-settings-additional-fields', [CompanySettingsAdditionalFieldsController::class, 'show']);
	Route::post('manage-company-settings-additional-fields', [CompanySettingsAdditionalFieldsController::class, 'upsert']);
	Route::delete('manage-company-settings-additional-fields', [CompanySettingsAdditionalFieldsController::class, 'destroy']);

	/*  */
	Route::get('manage-invoice-settings-company-details', [InvoiceSettingsCompanyDetailsController::class, 'show']);
	Route::post('manage-invoice-settings-company-details', [InvoiceSettingsCompanyDetailsController::class, 'saveOrUpdate']);

	/*
	Disabled as company details settings already have all fields and data / keeping this as if this may require or needed in the future.
	Route::get('manage-invoice-settings-company-address', [InvoiceSettingsCompanyAddressController::class, 'show']);
	Route::post('manage-invoice-settings-company-address', [InvoiceSettingsCompanyAddressController::class, 'saveOrUpdate']);
	*/

	Route::get('manage-invoice-settings-invoice-details', [InvoiceSettingsInvoiceDetailsController::class, 'show']);
	Route::post('manage-invoice-settings-invoice-details', [InvoiceSettingsInvoiceDetailsController::class, 'saveOrUpdate']);

	Route::get('manage-invoice-settings-additional-product-fields', [InvoiceSettingsAPFController::class, 'show']);
	Route::post('manage-invoice-settings-additional-product-fields', [InvoiceSettingsAPFController::class, 'upsert']);
	Route::delete('manage-invoice-settings-additional-product-fields/{id}', [InvoiceSettingsAPFController::class, 'destroy']);


	Route::get('manage-invoice-settings-product-columns', [InvoiceSettingsProductColumnsController::class, 'show']);
	Route::post('manage-invoice-settings-product-columns', [InvoiceSettingsProductColumnsController::class, 'saveOrUpdate']);

	Route::get('manage-invoice-settings-total-fields', [InvoiceSettingsTotalFieldsController::class, 'show']);
	Route::post('manage-invoice-settings-total-fields', [InvoiceSettingsTotalFieldsController::class, 'saveOrUpdate']);

	/**
	 * invoices start
	 */
	
	Route::patch('manage-invoices/apply-unapply-credits/apply-unapply-credits', [InvoiceController::class, 'ApplyUnapplyCredits']);
	Route::get('manage-invoices/apply-unapply-credits/fetch-already-applied', [InvoiceController::class, 'ApplyUnapplyCreditsFetchAlreadyApplied']);
	Route::get('manage-invoices/apply-unapply-credits/fetch-invoice/{id}', [InvoiceController::class, 'ApplyUnapplyCreditsFetchInvoice']);
	Route::get('manage-invoices/apply-unapply-credits/search-credits', [InvoiceController::class, 'ApplyUnapplyCreditsSearchCredits']);
	
	Route::get('manage-invoices/snapshot/{id}', [InvoiceController::class, 'snapshot']);
	Route::get('manage-invoices/download-pdf', [InvoiceController::class, 'downloadPdf']);
	//Route::get('manage-invoices/download-pdf', [InvoiceController::class, 'downloadPdf']);
	Route::patch('manage-invoices/toggle-cancel', [InvoiceController::class, 'toggleCancel']);
	// Route::get('manage-invoices/fetch-for-view', [InvoiceController::class, 'fetchForView']);

	Route::get('manage-invoices/fetch-clients', [InvoiceController::class, 'searchClients']);
	Route::get('manage-invoices/fetch-initial-data', [InvoiceController::class, 'fetchInitialData']);
	Route::get('manage-invoices/fetch-products', [InvoiceController::class, 'fetchProducts']);
	//Route::post('manage-invoices', [InvoiceController::class, 'store']);
	Route::get('manage-invoices/fetch-arranged-columns', [InvoiceController::class, 'fetchArrangedColumns']);
	Route::post('manage-invoices/save-arranged-columns', [InvoiceController::class, 'saveArrangedColumns']);
	Route::post('manage-invoices/add-credit-or-payment', [InvoiceController::class, 'addCreditOrPayment']);
	Route::get('manage-invoices/send-invoice', [InvoiceController::class, 'sendInvoice']);
	Route::resource('manage-invoices', InvoiceController::class)->except(array_merge(config('global.skip_routes'), ['destroy']));
	Route::delete('manage-invoices', [InvoiceController::class, 'destroy']);
	/**
	 * invoiced end
	 */
	

	Route::get('manage-email-settings-content', [EmailSettingsContentController::class, 'show']);
	Route::post('manage-email-settings-content', [EmailSettingsContentController::class, 'upsert']);

	Route::get('manage-email-settings-reminders', [EmailSettingsRemindersController::class, 'show']);
	Route::post('manage-email-settings-reminders', [EmailSettingsRemindersController::class, 'upsert']);

	Route::get('manage-email-settings-smtp', [EmailSettingsSMTPController::class, 'show']);
	Route::post('manage-email-settings-smtp', [EmailSettingsSMTPController::class, 'upsert']);


	Route::get('manage-payments-settings', [PaymentSettingsController::class, 'show']);
	Route::get('manage-paypal-settings', [PaymentSettingsPaypalController::class, 'show']);
	Route::post('manage-paypal-settings', [PaymentSettingsPaypalController::class, 'upsert']);
	Route::delete('manage-paypal-settings', [PaymentSettingsPaypalController::class, 'destroy']);

	Route::get('manage-stripe-settings', [PaymentSettingsStripeController::class, 'show']);
	Route::post('manage-stripe-settings', [PaymentSettingsStripeController::class, 'upsert']);
	Route::delete('manage-stripe-settings', [PaymentSettingsStripeController::class, 'destroy']);

	/**
	 * login settings
	 */
	Route::get('manage-login-settings', [LoginSettingsController::class, 'show']);
	Route::patch('manage-login-settings', [LoginSettingsController::class, 'update']);

	/**
	 * manage transactions routes
	 */
	Route::get('manage-transactions/fetch-arranged-columns', [TransactionsController::class, 'fetchArrangedColumns']);
	Route::post('manage-transactions/save-arranged-columns', [TransactionsController::class, 'saveArrangedColumns']);
	Route::get('manage-transactions/{id}', [TransactionsController::class, 'show']);
	Route::get('manage-transactions', [TransactionsController::class, 'index']);

	/**
	 * manage credits
	 */
	Route::get('manage-credits/fetch-already-applied', [CreditsController::class, 'fetchAlreadyApplied']);
	Route::patch('manage-credits/apply-unapply-credit', [CreditsController::class, 'applyUnapplyCredit']);
	Route::get('manage-credits/apply-unapply-fetch-credit', [CreditsController::class, 'applyUnapplyFetchCredit']);
	Route::get('manage-credits/apply-unapply-search-invoices', [CreditsController::class, 'applyUnapplySearchInvoices']);
	Route::get('manage-credits/fetch-arranged-columns', [CreditsController::class, 'fetchArrangedColumns']);
	Route::post('manage-credits/save-arranged-columns', [CreditsController::class, 'saveArrangedColumns']);
	
	Route::get('manage-credits/edit/{id}', [CreditsController::class, 'show']);
	Route::get('manage-credits/view/{id}', [CreditsController::class, 'show']);
	Route::get('manage-credits', [CreditsController::class, 'index']);
	Route::post('manage-credits', [CreditsController::class, 'store']);
	Route::patch('manage-credits/{id}', [CreditsController::class, 'update']);
	Route::delete('manage-credits', [CreditsController::class, 'destroy']);

	/**
	 * payment types
	 */
	Route::post('manage-payment-types', [PaymentTypeController::class, 'store']);
	Route::patch('manage-payment-types/{id}', [PaymentTypeController::class, 'update']);
	Route::delete('manage-payment-types', [PaymentTypeController::class, 'destroy']);
	Route::get('manage-payment-types', [PaymentTypeController::class, 'index']);
	Route::get('manage-payment-types/{id}', [PaymentTypeController::class, 'show']);

	/**
	 * payment requests
	 */
	Route::get('manage-payment-requests/fetch-arranged-columns', [PaymentRequestsController::class, 'fetchArrangedColumns']);
	Route::post('manage-payment-requests/save-arranged-columns', [PaymentRequestsController::class, 'saveArrangedColumns']);
	Route::get('manage-payment-requests/fetch-init', [PaymentRequestsController::class, 'fetchInit']);
	Route::post('manage-payment-requests', [PaymentRequestsController::class, 'store']);
	Route::get('manage-payment-requests', [PaymentRequestsController::class, 'index']);
	Route::patch('manage-payment-requests/send/{id}', [PaymentRequestsController::class, 'send']);
	Route::patch('manage-payment-requests/mark-sent/{id}', [PaymentRequestsController::class, 'send']);
	Route::patch('manage-payment-requests/cancel/{id}', [PaymentRequestsController::class, 'cancel']);
	Route::patch('manage-payment-requests/completed/{id}', [PaymentRequestsController::class, 'completed']);
	Route::patch('manage-payment-requests/{id}', [PaymentRequestsController::class, 'update']);
	Route::delete('manage-payment-requests', [PaymentRequestsController::class, 'destroy']);
	Route::get('manage-payment-requests/payment-types', [PaymentRequestsController::class, 'fetchPaymentTypes']);
	Route::get('manage-payment-requests/{id}', [PaymentRequestsController::class, 'show']);

	/**
	 * manage payments
	 */
	Route::get('manage-payments/fetch-already-applied', [PaymentsController::class, 'fetchAlreadyApplied']);
	Route::patch('manage-payments/apply-unapply-payment', [PaymentsController::class, 'applyUnapplyPayment']);
	Route::get('manage-payments/apply-unapply-fetch-payment', [PaymentsController::class, 'applyUnapplyFetchPayment']);
	Route::get('manage-payments/apply-unapply-search-invoices', [PaymentsController::class, 'applyUnapplySearchInvoices']);
	Route::get('manage-payments/fetch-arranged-columns', [PaymentsController::class, 'fetchArrangedColumns']);
	Route::post('manage-payments/save-arranged-columns', [PaymentsController::class, 'saveArrangedColumns']);
	
	Route::get('manage-payments/edit/{id}', [PaymentsController::class, 'show']);
	Route::get('manage-payments/view/{id}', [PaymentsController::class, 'show']);
	Route::get('manage-payments', [PaymentsController::class, 'index']);
	Route::post('manage-payments', [PaymentsController::class, 'store']);
	Route::patch('manage-payments/{id}', [PaymentsController::class, 'update']);
	Route::delete('manage-payments', [PaymentsController::class, 'destroy']);
	
});
