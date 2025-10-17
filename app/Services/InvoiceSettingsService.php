<?php

namespace App\Services;

use App\Models\SettingsSection;
use App\Traits\SettingsDefault;

class InvoiceSettingsService{

	use SettingsDefault;

	private array $results = [];
	private int $company_id = 0;

	/**
	 * fetchInvoiceSettingResults function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function __construct(int $company_id) {
		
		$this->company_id = $company_id;
		$this->results = SettingsSection::where('company_id', '=', $company_id)->whereIn('type', [ISC_INVOICE_NUMBERS_TYPE, ISC_PRODUCT_COLUMNS_TYPE, ISC_INVOICE_TOTAL_FIELDS_TYPE])->get()->mapWithKeys(fn($s) => [$s->type => json_decode($s->settings_json, true)])->toArray();
	
	}

	/**
	 * findInvoiceSettingByType function
	 *
	 * @param string $type
	 * @return array
	 */
	public function findInvoiceSettingByType(string $type) : array {

		return $this->results[$type] ?? $this->getDefaultForType($type);

	}

	/**
	 * getDefaultForType function
	 *
	 * @param string $type
	 * @return array
	 */
	private function getDefaultForType(string $type): array {

        return match($type) {
            ISC_INVOICE_NUMBERS_TYPE     	=> $this->getDefaultInvoiceNumbersSettings(),
            ISC_PRODUCT_COLUMNS_TYPE     	=> $this->getDefaultProductColumnsSettings($this->company_id),
            ISC_INVOICE_TOTAL_FIELDS_TYPE 	=> $this->getDefaultTotalFieldsSettings(),
        };

    }

	/**
	 * fetchInvoiceNumbers function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function getInvoiceNumbers() : array {

		return $this->findInvoiceSettingByType(ISC_INVOICE_NUMBERS_TYPE);

	}

	/**
	 * fetchProductColumns function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function getProductColumns() : array {

		return $this->findInvoiceSettingByType(ISC_PRODUCT_COLUMNS_TYPE);

	}

	/**
	 * getTotalFields function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function getTotalFields() : array {

		return $this->findInvoiceSettingByType(ISC_INVOICE_TOTAL_FIELDS_TYPE);

	}

}