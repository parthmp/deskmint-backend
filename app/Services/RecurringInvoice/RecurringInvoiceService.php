<?php

namespace App\Services\RecurringInvoice;

use App\Enums\RecurringInvoices\Frequencies;
use App\Exceptions\RecurringInvoiceException;
use App\Helpers\Sanitize;
use App\Models\RecurringInvoicesCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Services\Invoice\InvoiceSettingsService;
use App\Services\Invoice\InvoiceValidationService;
use Illuminate\Http\Request;

class RecurringInvoiceService {

	public function __construct(
		private InvoiceValidationService $invoice_validation_service,
		private CustomFields $custom_fields,
		private InvoiceSettingsService $invoice_settings_service,
		private RecurringInvoiceValidationService $recurring_invoice_validation_service
	){}

	/**
	 * fetchInitialData function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchInitialData(Request $request, int $company_id) : array {

		if(!$this->invoice_validation_service->validateTimezoneOffeset($request)){
			throw new RecurringInvoiceException("Invalid request", "invalid_timezone", config('global.error_code'));
		}

		$timezone = (string) Sanitize::input($request->input('timezone'));

		$invoice_settings = $this->invoice_settings_service->setCompany($company_id);

		$fields = $this->custom_fields->fetchCustomFields(RecurringInvoicesCustomField::class, $company_id);

		$gateways = PaymentGateway::configuredOptions((int) $company_id);

		$freqs = Frequencies::dropdownData();
		
		return [
			'product_columns' 			=> 	$invoice_settings->getProductColumns(),
			'total_fields' 				=> 	$invoice_settings->getTotalFields(),
			'custom_fields'				=>	$this->custom_fields->printCustomFields($fields),
			'gateways'					=>	$gateways,
			'frequencies'				=>	$freqs,
			'custom_frequency_value'	=> Frequencies::CUSTOM->value,
			'none_gateway_value'		=> PaymentGateway::NONE->value
		];

	}

	/**
	 * validate function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function validate(Request $request, int $company_id) : bool {
		return $this->recurring_invoice_validation_service->validate($request, $company_id);
	}

}