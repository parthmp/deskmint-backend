<?php

namespace App\Services\Invoice;

use App\Helpers\Sanitize;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Modules\CustomFields\CustomFields;
use App\Modules\CustomFields\Exceptions\InvalidCustomFieldsException;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Invoice\Exceptions\InvoiceException;
use App\Services\Product\ProductFieldService;
use App\Services\Invoice\InvoiceSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceValidationService extends ProductFieldService {

	public function __construct(
		private CustomFields $custom_fields, 
		private InvoiceSettingsService $invoice_settings_service,
		private ProductRepository $product_repository,
		private ClientRepository $client_repository
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
			'settings.payment_method'				=>	'required',
			'settings.send_invoice_in_email'		=>	'required|boolean',
		]);

		return (bool) !$v->fails();

	}

	/**
	 * ifSubmittedFieldsAreSameAsDefined function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function ifSubmittedFieldsAreSameAsDefined(Request $request, int $company_id) : bool {

		$invoice_settings = $this->invoice_settings_service->setCompany($company_id);

		$product_columns = $invoice_settings->getProductColumns();

		$product_rows = $request->input('data.product_rows');

		/* now check if all fields exist */

		$fields_same = true;

		$product_row_fields_names = [];
		
		foreach($product_rows[0] as $key => $submitted_col){
			$product_row_fields_names[] = $key;
		}

		$custom_tax_ids = $this->getCustomTaxIds($company_id);

		foreach($product_columns as $user_defined_column){
			
			/* this "normal is for normal fields from DB, not for taxes" */
			if($user_defined_column['mapped'] !== null && $user_defined_column['type'] === 'normal' && !in_array($user_defined_column['mapped'][0], $product_row_fields_names)){
				$fields_same = false;
				break;
			}

			
			if($user_defined_column['mapped'] === null && $user_defined_column['type'] === 'custom'){
				
				if(!isset($user_defined_column['id_column'])){
					$fields_same = false;
					break;
				}

				$custom_field_name = $this->generateFieldName($user_defined_column, $custom_tax_ids);
				
				if(!in_array($custom_field_name, $product_row_fields_names)){
					$fields_same = false;
					break;
				}

			}

			
		}

		return $fields_same;

	}

	/**
	 * shouldHaveAtLeastOneRow function
	 *
	 * @param array $product_rows
	 * @param integer $company_id
	 * @return boolean
	 */
	private function shouldHaveAtLeastOneRow(array $product_rows, int $company_id) : bool {

		if(empty($product_rows)){
			return false;
		}

		// Extract all product IDs
		$product_ids = [];
		foreach($product_rows as $row){
			
			if(trim($row['row_uuid']) === ''){
				throw new InvoiceException('Please have at least one product to create invoice', 'invalid_product_uuid_tab0', config('global.error_code'), 0);
			}

			if(!empty($row['product_id'])){
				$product_ids[] = (int) $row['product_id'];
			}
		}

		if(empty($product_ids)){
			return false;
		}

		// Check if at least one exists in database for this company
		return $this->product_repository->ifProductsExists($company_id, $product_ids);

	}

	/**
	 * validatePaymentGatewayCurrency function
	 *
	 * @param integer $client_id
	 * @param integer $payment_method
	 * @return array
	 */
	private function validatePaymentGatewayCurrency(int $client_id, int $payment_method) : array {
		
		$currency = $this->client_repository->fetchClientCurrencyById($client_id);
		
		$currency_code = strtoupper(trim($currency->code));

		$is_valid = true;

		if($payment_method === PAYMENT_PAYPAL){
			$is_valid = in_array($currency_code, config('payment.supported_currencies.paypal'));
		}else if($payment_method === PAYMENT_STRIPE){
			$is_valid = in_array($currency_code, config('payment.supported_currencies.stripe'));
		}

		return ['code' => $currency_code, 'valid' => $is_valid];

	}

	/**
	 * getPaymentMethodName function
	 *
	 * @param integer $payment_method
	 * @return string
	 */
	private function getPaymentMethodName(int $payment_method) : string {
		return match($payment_method){
			PAYMENT_CASH			=>	'Cash',
			PAYMENT_NETBANKING		=>	'Netbanking',
			PAYMENT_PAYPAL			=>	'PayPal',
			PAYMENT_STRIPE			=>	'Stripe'
		};
	}
	
	/**
	 * validateAllForInvoice function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function validateAllForInvoice(Request $request, int $company_id) : bool {

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
			throw new InvoiceException('Please fill in required fields', 'invalid_request', config('global.error_code'), 2);
		}
		
		if(!$this->ifSubmittedFieldsAreSameAsDefined($request, $company_id)){
			throw new InvoiceException('Invalid request', 'mismatch_fields', config('global.error_code'), 2);
		}

		$client_id = (int) Sanitize::input($request->input('data.invoice_details.client.client_id'));
		$payment_method = (int) Sanitize::input($request->input('settings.payment_method'));
		$currency_validated = $this->validatePaymentGatewayCurrency($client_id, $payment_method);

		if(!$currency_validated['valid']){
			$payment_gateway_name = $this->getPaymentMethodName($payment_method);
			throw new InvoiceException('Currency '.$currency_validated['code'].' not supported with '.$payment_gateway_name, 'unsupported_currency', config('global.error_code'), 2);
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
			'timezone_offset_minutes'	=>	'required'
		]);

		return !$v->fails();

	}

}