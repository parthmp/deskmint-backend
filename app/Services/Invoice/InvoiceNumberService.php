<?php

namespace App\Services\Invoice;

use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Services\HandleInvoiceNumbers;
use App\Services\Invoice\InvoiceSettingsService;

class InvoiceNumberService{

	public function __construct(
		private SettingsSectionRepository $settings_section_repository,
		private InvoiceRepository $invoice_repository,
		private InvoiceSettingsService $invoice_settings_service
	){}

	/**
	 * resetManualInvoieNumberResetFlag function
	 *
	 * @param integer $company_id
	 * @return void
	 */
	public function resetManualInvoieNumberResetFlag(int $company_id) : void {
		
		$setting = $this->settings_section_repository->fetchSettings($company_id, ISC_INVOICE_NUMBER_RESET_TYPE);

		if($setting){
			$json = json_decode($setting->settings_json, true);
			$json['reset'] = 0;
			$setting->settings_json = json_encode($json);
			$setting->save();
		}

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

		$invoice = $this->invoice_repository->fetchInvoiceByNumber($invoice_number, $company_id, true);

		if(!$invoice){
			return $invoice_number;
		}

		if($invoice->pattern_matched === 0){
			return 'copy - '.$invoice->invoice_number.' original '.$invoice->id;
		}
		
		$settings = $this->invoice_settings_service->setCompany((int) $company_id);
		
		return (new HandleInvoiceNumbers((int) $company_id, $settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->getNextInvoiceNumber();

	}

	/**
	 * sanitizeInvoiceNumber function
	 *
	 * @param string $invoice_number
	 * @return string
	 */
	public function sanitizeInvoiceNumber(string $invoice_number): string {

		$invoice_number = preg_replace('/[\x00-\x1F\x7F]/', '', $invoice_number);

		$problematic_chars = [
			'/', '\\', '#', '?', '&', '%', "'", '"', ';', '`',
			'$', '^', '*', '+', '=', '|', '<', '>', '[', ']',
			'{', '}', '~', '!'
		];
		
		$invoice_number = str_replace($problematic_chars, '', $invoice_number);
		
		$invoice_number = trim($invoice_number);
	
		return $invoice_number;

	}

}