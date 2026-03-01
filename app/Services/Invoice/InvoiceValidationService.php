<?php

namespace App\Services\Invoice;

use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Modules\CustomFields\CustomFields;
use App\Modules\CustomFields\Exceptions\InvalidCustomFieldsException;
use App\Services\Invoice\Exceptions\InvoiceException;
use App\Services\Product\ProductFieldService;
use App\Services\Invoice\InvoiceSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class InvoiceValidationService extends ProductFieldService {

	public function __construct(private CustomFields $custom_fields, private InvoiceSettingsService $invoice_settings_service){}

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

	private function shouldHaveAtLeastOneRow(array $product_rows, int $company_id){

		if(empty($product_rows)){
			return false;
		}

		// Extract all product IDs
		$product_ids = [];
		foreach($product_rows as $row){
			if(!empty($row['product_id'])){
				$product_ids[] = (int) $row['product_id'];
			}
		}

		if(empty($product_ids)){
			return false;
		}

		// Check if at least one exists in database for this company
		return Product::where('company_id', $company_id)->whereIn('id', $product_ids)->exists();

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
			throw new InvoiceException('Please fill in required fields', 'invalid_request', config('global.error_code'), 0);
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
		
		if(!$request->filled('data.product_rows')){
			throw new InvoiceException('Please have at least one product to create invoice', 'invalid_request', config('global.error_code'), 0);
		}

		$product_rows = $request->input('data.product_rows');

		if(!$this->shouldHaveAtLeastOneRow($product_rows, $company_id)){
			throw new InvoiceException('Please have at least one product to create invoice', 'invalid_request', config('global.error_code'), 0);
		}
		
		if(!$this->ifSubmittedFieldsAreSameAsDefined($request, $company_id)){
			throw new InvoiceException('Invalid request', 'mismatch_fields', config('global.error_code'), 2);
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