<?php

namespace App\Services\Invoice;

use App\Helpers\Sanitize;
use App\Models\InvoicesCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Services\HandleInvoiceNumbers;
use App\Services\Invoice\Exceptions\InvoiceException;
use Illuminate\Http\Request;

class InvoiceFetchService{

	public function __construct(
		private InvoiceValidationService $invoice_validation_service,
		private InvoiceSettingsService $invoice_settings_service,
		private CustomFields $custom_fields,
		private SettingsSectionRepository $settings_section_repository
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
			throw new InvoiceException("Invalid request", "invalid_timezone", config('global.error_code'));
		}

		$timezone_offset_minutes = (int) Sanitize::input($request->input('timezone_offset_minutes'));

		$invoice_settings = $this->invoice_settings_service->setCompany($company_id);

		$fields = $this->custom_fields->fetchCustomFields(InvoicesCustomField::class, $company_id);

		// /* get payment integration data */
		$gateways = $this->settings_section_repository->getGateWayNames((int) $company_id);

		return [
			'invoice_number'	=>	(new HandleInvoiceNumbers((int) $company_id, $invoice_settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->getNextInvoiceNumber(),
			'product_columns' 	=> 	$invoice_settings->getProductColumns(),
			'total_fields' 		=> 	$invoice_settings->getTotalFields(),
			'custom_fields'		=>	$this->custom_fields->printCustomFields($fields),
			'gateways'			=>	$gateways
		];
		
	}

}