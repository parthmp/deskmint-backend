<?php

namespace App\Services\Invoice;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalProductColumnsField;
use App\Models\AdditionalProductColumnsFieldValue;
use App\Models\Invoice;
use App\Services\Product\ProductFieldService;
use Illuminate\Http\Request;

class InvoiceService{

	private InvoiceValidationService $invoice_validation_service;
	private ProductFieldService $product_field_service;
	private InvoiceNumberService $invoice_number_service;
	private InvoiceCalculationService $invoice_calculation_service;

	public function __construct(InvoiceValidationService $invoice_validation_service, ProductFieldService $product_field_service, InvoiceNumberService $invoice_number_service, InvoiceCalculationService $invoice_calculation_service){
		$this->invoice_validation_service = $invoice_validation_service;
		$this->product_field_service = $product_field_service;
		$this->invoice_number_service = $invoice_number_service;
		$this->invoice_calculation_service = $invoice_calculation_service;
	}

	/**
	 * validateInvoiceDetails function
	 *
	 * @param Request $request
	 * @return bool
	 */
	public function validateInvoiceDetails(Request $request) : bool {
		return $this->invoice_validation_service->validateInvoiceDetails($request);
	}

	/**
	 * validateInvoiceSettings function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateInvoiceSettings(Request $request) : bool {
		return $this->invoice_validation_service->validateInvoiceSettings($request);
	}

	/**
	 * ifSubmittedFieldsAreSameAsDefined function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function ifSubmittedFieldsAreSameAsDefined(Request $request, int $company_id) : bool {
		return $this->invoice_validation_service->ifSubmittedFieldsAreSameAsDefined($request, $company_id);
	}

	/**
	 * insertProductRows function
	 *
	 * @param Request $request
	 * @param integer $invoice_id
	 * @param integer $company_id
	 * @return void
	 */
	public function insertProductRows(Request $request, int $invoice_id, int $company_id) : void {
		$this->product_field_service->insertProductRows($request, $invoice_id, $company_id);
	}

	/**
	 * resetManualInvoieNumberResetFlag function
	 *
	 * @param integer $company_id
	 * @return void
	 */
	public function resetManualInvoieNumberResetFlag(int $company_id) : void {
		$this->invoice_number_service->resetManualInvoieNumberResetFlag($company_id);
	}

	/**
	 * getInvoiceNumber function
	 *
	 * @param string $invoice_number
	 * @param integer $company_id
	 * @param integer $timezone_offset_minutes
	 * @return string
	 */
	public function getInvoiceNumber(string $invoice_number, int $company_id, int $timezone_offset_minutes) : string {
		return $this->invoice_number_service->getInvoiceNumber($invoice_number, $company_id, $timezone_offset_minutes);
	}

	/**
	 * sanitizeInvoiceNumber function
	 *
	 * @param string $invoice_number
	 * @return string
	 */
	public function sanitizeInvoiceNumber(string $invoice_number): string {
		return $this->invoice_number_service->sanitizeInvoiceNumber($invoice_number);
	}

	/**
	 * getDiscountNumberAndType function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function getDiscountNumberAndType(Request $request) : array {

		$discount_number = 0;

		if($request->filled('data.invoice_details.global_discount')){
			$discount_number = (float) Sanitize::input($request->input('data.invoice_details.global_discount'));
		}

		$discount_type = 'percentage';

		if($request->filled('data.invoice_details.global_discount_type')){
			$discount_type = Sanitize::input($request->input('data.invoice_details.global_discount_type'));
			if($discount_type !== 'percentage'){
				$discount_type = 'amount';
			}else{
				$discount_number = max(0, min(100, (double) $discount_number));
			}
		}

		return [
			'discount_type'		=>	$discount_type,
			'discount_number'	=>	$discount_number
		];

	}

	/**
	 * validateAllForInvoice function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return mixed
	 */
	public function validateAllForInvoice(Request $request, int $company_id) : mixed{
		return $this->invoice_validation_service->validateAllForInvoice($request, $company_id);
	}
	
	public function calculateInvoice(array $product_rows, string $discount_type, int $discount_number) : array {
		return $this->invoice_calculation_service->calculateInvoice($product_rows, $discount_type, $discount_number);
	}
}