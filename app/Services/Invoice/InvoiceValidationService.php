<?php

namespace App\Services\Invoice;

use App\Helpers\Sanitize;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Modules\CustomFields\CustomFields;
use App\Modules\CustomFields\Exceptions\InvalidCustomFieldsException;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Invoice\Exceptions\InvoiceException;
use App\Services\Product\ProductFieldService;
use App\Services\Invoice\InvoiceSettingsService;
use App\Traits\InvoiceValidation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceValidationService extends ProductFieldService {

	use InvoiceValidation;

	public function __construct(
		private CustomFields $custom_fields, 
		private InvoiceSettingsService $invoice_settings_service,
		private ProductRepository $product_repository,
		private ClientRepository $client_repository,
		private InvoiceRepository $invoice_repository
	){}

	/**
	 * validateInvoiceDetails function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateInvoiceDetails(Request $request) : bool {
		
		$v = Validator::make($request->all(), [
			'data.invoice_details.client.client_id'		=>	'required|exists:clients,id',
			'data.invoice_details.invoice_date.value'	=>	'required',
			'data.invoice_details.invoice_number.value'	=>	'required',
			'data.invoice_details.due_date.value'		=>	'required'
		]);
		
		return (bool) !$v->fails();
	}

	/**
	 * validateInvoiceSettings function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateInvoiceSettings(Request $request) : bool {

		$v = Validator::make($request->all(), [
			'settings.payment_gateway'				=>	'required',
			'settings.send_invoice_in_email'		=>	'required|boolean',
		]);

		
		return (bool) !$v->fails();

	}

	

	
	/**
	 * validateAllForInvoice function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function validateAllForInvoice(Request $request, int $company_id, int $invoice_id = 0) : bool {

		if($invoice_id > 0){

			$invoice = $this->invoice_repository->fetchInvoiceObjById($invoice_id, $company_id);

			if(!$invoice){
				throw new InvoiceException('Invalid data provided', 'invalid_request_tab2', config('global.error_code'), 2);
			}

			if((int) $invoice->status === (int) InvoiceStatus::PAID->value || (int) $invoice->status === (int) InvoiceStatus::PARTIALLY_PAID->value){
				throw new InvoiceException('Readonly : This invoice has payments attached to it', 'invalid_payment_attached', config('global.error_code'), 2);
			}

			if((int) $invoice->status === (int) InvoiceStatus::CANCELLED->value){
				throw new InvoiceException('Readonly : This invoice has been cancelled', 'invalid_invoice_cancelled', config('global.error_code'), 2);
			}

		}

		$tab0_valid = $this->validateInvoiceDetails($request);
		
		if(!$tab0_valid){
			throw new InvoiceException('Please fill in required fields', 'invalid_request_tab0', config('global.error_code'), 0);
		}

		if(!$request->filled('data.product_rows')){
			throw new InvoiceException('Please have at least one product to create invoice', 'invalid_request_product_rows_tab0', config('global.error_code'), 0);
		}

		$product_rows = $request->input('data.product_rows');

		if(!$this->shouldHaveAtLeastOneRow($product_rows, $company_id)){
			throw new InvoiceException('Please have at least one product to create invoice', 'invalid_product_data_tab0', config('global.error_code'), 0);
		}

		try{

			$this->custom_fields->validateCustomFields($request, InvoicesCustomField::class, 'invalid_data_tab1', 1);

		}catch(InvalidCustomFieldsException $e){
			throw new InvoiceException($e->getMessage(), $e->getValidity(), $e->getCode(), $e->getTab());
		}

		$tab2_valid = $this->validateInvoiceSettings($request);

		if(!$tab2_valid){
			throw new InvoiceException('Please fill in required fields', 'invalid_request_tab2', config('global.error_code'), 2);
		}
		
		if(!$this->ifSubmittedFieldsAreSameAsDefined($request, $company_id)){
			throw new InvoiceException('Invalid request', 'mismatch_fields', config('global.error_code'), 2);
		}

		$client_id = (int) Sanitize::input($request->input('data.invoice_details.client.client_id'));
		
		$payment_gateway_number = Sanitize::input($request->input('settings.payment_gateway'));

		$currency_validated = $this->validatePaymentGatewayCurrency($client_id, $payment_gateway_number);

		
		if(!PaymentGateway::paymentGatewayExists((int) $payment_gateway_number)){
			throw new InvoiceException('Invalid request', 'invalid_payment_gateway', config('global.error_code'), 2);
		}

		if(!$currency_validated['valid']){
			
			
			throw new InvoiceException('Currency '.$currency_validated['code'].' not supported with '.PaymentGateway::getLabelByValue($payment_gateway_number), 'unsupported_currency', config('global.error_code'), 2);
		}

		if(!$request->has('timezone')){
			throw new InvoiceException('Invalid request', 'invalid_timezone', config('global.error_code'), 2);
		}

		$timezone = (string) Sanitize::input($request->input('timezone'));
		
		try{
			new \DateTimeZone($timezone);
		}catch(\Exception $e){
			throw new InvoiceException('Invalid request', 'invalid_timezone', config('global.error_code'), 2);
		}

		return true;

	}

	/**
	 * validateTimezoneOffeset function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateTimezoneOffeset(Request $request) : bool {

		$v = Validator::make($request->all(), [
			'timezone'	=>	[
							'required',
							'string',
							function ($attribute, $value, $fail) {
								try {
									new \DateTimeZone($value);
								} catch (\Exception $e) {
									$fail("The {$attribute} must be a valid timezone.");
								}
							},
						]
		]);

		return !$v->fails();

	}

	/**
	 * validateIssueRefunds function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateIssueRefunds(Request $request) : bool {

		if(!$request->has('refund_full') || !$request->has('company_id') || !$request->has('refund_entries')){
			throw new InvoiceException("Invalid data provided", "invalid_data", (int) config('global.error_code'));
		}

		$full_refund = (bool) $request->input('refund_full');
		$company_id = (int) Sanitize::input($request->input('company_id'));

		if(!$full_refund){

			$refund_entries = (array) $request->input('refund_entries');

			if(empty($refund_entries)){
				throw new InvoiceException("Please select at least one entry to refund from dropdown", "refund_entries_empty", (int) config('global.error_code'));
			}

			$ids = [];

			foreach($refund_entries as $entry){
				
				$id = $entry['meta']['id'] ?? null;
				if(!$id){
					throw new InvoiceException("Invalid refund entries provided", "invalid_refund_entries", (int) config('global.error_code'));
				}

				$ids[] = $id;

			}

			$ledger = $this->invoice_repository->fetchInvoiceLedgerEntriesByIds($company_id, $ids);

			if((int) count($ids) !== (int) $ledger->count()){
				throw new InvoiceException("Refund entries do not match", "refund_entries_unmatch", (int) config('global.error_code'));
			}

			foreach($ledger as $ledger_entry){

				foreach($refund_entries as $refund_entry){

					$refund_entry_id = (int) Sanitize::input((string) $refund_entry['meta']['id']);

					if((int) $ledger_entry->id === $refund_entry_id){

						$refund_entry_filled_amount = Sanitize::input($refund_entry['meta']['filled_amount']);

						if((string) $refund_entry_filled_amount === ''){
							throw new InvoiceException("Please enter the refund amount for #".$refund_entry_id, "filled_data_empty", (int) config('global.error_code'));
						}

						$already_refunded = BigDecimal::of($ledger_entry->total_applied_refund);
						$already_amount = BigDecimal::of($ledger_entry->total_applied);

						$allowed = $already_amount->minus($already_refunded);

						if($allowed->isLessThan(BigDecimal::of(0))){
							$allowed = BigDecimal::of(0);
						}

						$filled_amount = BigDecimal::of($refund_entry_filled_amount);

						if($filled_amount->isGreaterThan($allowed)){
							$allowed_string = $allowed->toScale(2, RoundingMode::HalfUp)->__toString();
							throw new InvoiceException("Allowed refund amount for #".$refund_entry_id." is ".$allowed_string, "not_allowed_amount", (int) config('global.error_code'));
						}

					}

				}

			}



		}


		return true;

	}

}