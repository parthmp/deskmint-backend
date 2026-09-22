<?php

namespace App\Services\RecurringInvoice;

use App\Exceptions\RecurringInvoiceException;
use App\Helpers\Sanitize;
use App\Models\RecurringInvoicesCustomField;
use App\Traits\InvoiceValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\CustomFields\CustomFields;
use App\Modules\CustomFields\Exceptions\InvalidCustomFieldsException;
use App\Modules\Payment\Enums\PaymentGateway;

class RecurringInvoiceValidationService {

	use InvoiceValidation;

	public function __construct(
		private CustomFields $custom_fields
	){}

	/**
	 * validateDetails function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateDetails(Request $request) : bool {
		
		$v = Validator::make($request->all(), [
			'data.invoice_details.client.client_id'		=>	'required|exists:clients,id'
		]);
		
		return (bool) !$v->fails();
	}

	/**
	 * validateSettings function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateSettings(Request $request) : bool {

		$v = Validator::make($request->all(), [
			'settings.payment_gateway'				=>	'required',
			'settings.send_invoice'					=>	'required|boolean',
			'settings.start_subscription'			=>	'required|boolean',
			'settings.frequency'					=>	'required|integer'
		]);

		if(!$request->has('custom_frequency_days') && (int) $request->input('payment_gateway') === PaymentGateway::NONE->value){
			return false;
		}
		
		return (bool) !$v->fails();

	}

	/**
	 * validate function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function validate(Request $request, int $company_id) : bool {

		$tab0_valid = $this->validateDetails($request);
		
		if(!$tab0_valid){
			throw new RecurringInvoiceException('Please fill in required fields', 'invalid_request_tab0', config('global.error_code'), 0);
		}

		if(!$request->filled('data.product_rows')){
			throw new RecurringInvoiceException('Please have at least one product to create recurring invoice', 'invalid_request_product_rows_tab0', config('global.error_code'), 0);
		}

		$product_rows = $request->input('data.product_rows');

		if(!$this->shouldHaveAtLeastOneRow($product_rows, $company_id, RecurringInvoiceException::class)){
			throw new RecurringInvoiceException('Please have at least one product to create recurring invoice', 'invalid_product_data_tab0', config('global.error_code'), 0);
		}

		try{

			$this->custom_fields->validateCustomFields($request, RecurringInvoicesCustomField::class, 'invalid_data_tab1', 1);

		}catch(InvalidCustomFieldsException $e){
			throw new RecurringInvoiceException($e->getMessage(), $e->getValidity(), $e->getCode(), $e->getTab());
		}

		$tab2_valid = $this->validateSettings($request);

		if(!$tab2_valid){
			throw new RecurringInvoiceException('Please fill in required fields', 'invalid_request_tab2', config('global.error_code'), 2);
		}

		if(!$this->ifSubmittedFieldsAreSameAsDefined($request, $company_id)){
			throw new RecurringInvoiceException('Invalid request', 'mismatch_fields', config('global.error_code'), 2);
		}

		$client_id = (int) Sanitize::input($request->input('data.invoice_details.client.client_id'));
		
		$payment_gateway_number = Sanitize::input($request->input('settings.payment_gateway'));

		$currency_validated = $this->validatePaymentGatewayCurrency($client_id, $payment_gateway_number);

		
		if(!PaymentGateway::paymentGatewayExists((int) $payment_gateway_number)){
			throw new RecurringInvoiceException('Invalid request', 'invalid_payment_gateway', config('global.error_code'), 2);
		}

		if(!$currency_validated['valid']){
			
			
			throw new RecurringInvoiceException('Currency '.$currency_validated['code'].' not supported with '.PaymentGateway::getLabelByValue($payment_gateway_number), 'unsupported_currency', config('global.error_code'), 2);
		}

		if(!$request->has('timezone')){
			throw new RecurringInvoiceException('Invalid request', 'invalid_timezone', config('global.error_code'), 2);
		}

		$timezone = (string) Sanitize::input($request->input('timezone'));
		
		try{
			new \DateTimeZone($timezone);
		}catch(\Exception $e){
			throw new RecurringInvoiceException('Invalid request', 'invalid_timezone', config('global.error_code'), 2);
		}

		return true;

	}

}