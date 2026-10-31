<?php

namespace App\Services\RecurringInvoice;

use App\Enums\RecurringInvoices\Frequencies;
use App\Enums\RecurringInvoices\RecurringInvoiceStatus;
use App\Exceptions\RecurringInvoiceException;
use App\Helpers\Sanitize;
use App\Models\Payment;
use App\Models\RecurringInvoicesCustomField;
use App\Traits\InvoiceValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\CustomFields\CustomFields;
use App\Modules\CustomFields\Exceptions\InvalidCustomFieldsException;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Repositories\RecurringInvoice\RecurringInvoiceRepository;
use App\Services\Invoice\InvoiceBaseService;
use App\Services\Invoice\InvoiceCalculationService;
use App\Traits\InvoiceBase;
use Brick\Math\BigDecimal;
use Carbon\Carbon;

class RecurringInvoiceValidationService {

	use InvoiceValidation, InvoiceBase;

	public function __construct(
		private CustomFields $custom_fields,
		private RecurringInvoiceRepository $recurring_invoice_repository,
		private InvoiceCalculationService $invoice_calculation_service,
		private InvoiceBaseService $invoice_base_service
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
			'settings.send_email'					=>	'required|boolean',
			'settings.send_email_for_invoice'		=>	'required|boolean',
			'settings.mark_invoices_paid'			=>	'required|boolean',
			'settings.frequency'					=>	'required|integer'
		]);

		if((!$request->has('settings.custom_frequency_days') || trim($request->input('settings.custom_frequency_days')) === '') && ((int) $request->input('settings.frequency') === Frequencies::CUSTOM->value)){
			return false;
		}

		if(!$request->has('settings.payment_type')){
			return false;
		}

		$payment_type = Sanitize::input($request->input('settings.payment_type'));
		$payment_gateway = Sanitize::input($request->input('settings.payment_gateway'));

		if((int) $payment_gateway === PaymentGateway::NONE->value){
			
			if($payment_type === '' || $payment_type === null){
				return false;
			}

			if(!$this->recurring_invoice_repository->paymentTypeExists((int) $payment_type)){
				return false;
			}

		}
		
		return (bool) !$v->fails();

	}

	/**
	 * validateStartDateTime function
	 *
	 * @param string $datetime
	 * @return boolean
	 */
	private function validateStartDateTime(string $datetime): bool {

		if(trim($datetime) === ''){
			return false;
		}

		if(Validator::make(['d' => $datetime], ['d' => 'required|date'])->fails()){
			return false;
		}

		try{
			$start_at = Carbon::parse($datetime)->utc();
		}catch(\Throwable $e){
			return false;
		}

		return $start_at->gte(now()->subMinutes(20));

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

		$start_datetime = (string) Sanitize::input($request->input('settings.subscription_start_datetime'));

		$payment_gateway_number = Sanitize::input($request->input('settings.payment_gateway'));

		if(!$this->validateStartDateTime($start_datetime) && (int) $payment_gateway_number === PaymentGateway::NONE->value){
			throw new RecurringInvoiceException('Please select future invoice generation datetime', 'invalid_request_tab2', config('global.error_code'), 2);
		}

		if(!$this->ifSubmittedFieldsAreSameAsDefined($request, $company_id)){
			throw new RecurringInvoiceException('Invalid request', 'mismatch_fields', config('global.error_code'), 2);
		}

		$client_id = (int) Sanitize::input($request->input('data.invoice_details.client.client_id'));
		
		
		$frequency = (int) Sanitize::input($request->input('settings.frequency'));
		$custom_frequency_days = (int) Sanitize::input($request->input('settings.custom_frequency_days'));

		$currency_validated = $this->validatePaymentGatewayCurrency($client_id, $payment_gateway_number);

		
		if(!PaymentGateway::paymentGatewayExists((int) $payment_gateway_number)){
			throw new RecurringInvoiceException('Invalid request', 'invalid_payment_gateway', config('global.error_code'), 2);
		}

		if(!Frequencies::exists((int) $frequency)){
			throw new RecurringInvoiceException('Invalid request', 'invalid_frequency', config('global.error_code'), 2);
		}

		if((int) $custom_frequency_days > 365){
			throw new RecurringInvoiceException('Custom frequency days must not be greater than 365 days', 'invalid_custom_frequency_days', config('global.error_code'), 2);
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

	/**
	 * validateForEdit function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @return boolean
	 */
	public function validateForEdit(Request $request, int $company_id, int $recurring_invoice_id) : bool {

		$error_code = (int) config('global.error_code');

		$recurring_invoice = $this->recurring_invoice_repository->fetchById($company_id, $recurring_invoice_id);

		if(!$recurring_invoice){
			throw new RecurringInvoiceException('invalid request', 'invalid_request_ri', $error_code, 2);
		}

		if((int) $recurring_invoice->status === RecurringInvoiceStatus::CANCELLED->value){
			throw new RecurringInvoiceException('You can not modify cancelled recurring invoice', 'cancelled', $error_code, 2);
		}

		$db_payment_gateway = (int) $recurring_invoice->payment_gateway;

		//paypal not allowed.
		if($db_payment_gateway === PaymentGateway::PAYPAL->value){
			
			if((int) $recurring_invoice->status === RecurringInvoiceStatus::ACTIVE->value || (int) $recurring_invoice->status === RecurringInvoiceStatus::PAUSED->value){
				throw new RecurringInvoiceException('You can not modify this recurring invoice due to paypal api limitations', 'paypal_restricted', $error_code, 2);
			}

		}

		if((int) $recurring_invoice->payment_gateway === PaymentGateway::STRIPE->value){

			$discount_array = $this->invoice_base_service->getDiscountNumberAndType($request);
			$discount_type = 'amount';
			if($discount_array['discount_type'] === 'percentage'){
				$discount_type = 'percentage';
			}
			
			$changed = false;

			if((int) $recurring_invoice->status === RecurringInvoiceStatus::ACTIVE->value){

				$product_rows = $this->filterValidProductRows($request->input('data.product_rows'), $company_id);
				$totals = $this->invoice_calculation_service->calculateInvoice($product_rows, $discount_type, $discount_array['discount_number']);
				$global_total = $totals['global_total'];

				$frequency = (int) Sanitize::input($request->input('settings.frequency'));
				$custom_frequency_days = (int) Sanitize::input($request->input('settings.custom_frequency_days'));
				//check if frequency changed or total changed
				if((int) $recurring_invoice->frequency !== $frequency){
					$changed = true;
				}else if((int) $recurring_invoice->frequency === $frequency && (int) $recurring_invoice->custom_frequency_days !== $custom_frequency_days && $frequency === Frequencies::CUSTOM->value){
					$changed = true;
				}else if(!BigDecimal::of($recurring_invoice->total)->isEqualTo(BigDecimal::of($global_total))){
					$changed = true;
				}

				if($changed){

					//now check if user wanted the custom datetime and validate.
					$charge_now = (bool) Sanitize::input($request->input('settings.charge_now'));
					if(!$charge_now){
						
						$start_datetime = (string) Sanitize::input($request->input('settings.subscription_start_datetime'));
						
						$start = Carbon::parse($start_datetime, 'UTC')->utc();
						$minimum_start = Carbon::now('UTC')->addHours(24);

						if($start->lt($minimum_start)){
							throw new RecurringInvoiceException('Invoice generation date must be ahead more than 24 hours from now', 'not_allowed_24_hours', $error_code, 2);
						}

					}

				}

			}

		}

		return true;

	}

}