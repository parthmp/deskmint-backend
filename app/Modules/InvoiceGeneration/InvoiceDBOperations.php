<?php

namespace App\Modules\InvoiceGeneration;

use App\Models\ClientCustomFieldValue;
use App\Models\ClientsCustomField;
use App\Models\Invoice;
use App\Models\SettingsSection;
use Illuminate\Database\Eloquent\Collection;

/**
 * InvoiceDBOperations class
 */
class InvoiceDBOperations{
	
	private int $company_id;
	private int $invoice_id;

	/**
	 * __construct function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 */
	public function __construct(int $company_id, int $invoice_id){
		$this->company_id = $company_id;
		$this->invoice_id = $invoice_id;
	}

	
	/**
	 * fetchGeneralSettings function
	 *
	 * @return SettingsSection|null
	 */
	public function fetchGeneralSettings() : SettingsSection|null {
		return SettingsSection::where([['company_id', '=', $this->company_id], ['type', '=', ISC_INVOICE_GENERAL_DETAILS_TYPE]])->first();
	}

	/**
	 * fetchInvoiceRow function
	 *
	 * @return Invoice|null
	 */
	public function fetchInvoiceRow() : Invoice|null {
		return Invoice::where('id', '=', $this->invoice_id)->withTrashed()->first();
	}

	/**
	 * fetchClientDetailsSettings function
	 *
	 * @return SettingsSection|null
	 */
	public function fetchClientDetailsSettings() : SettingsSection|null {
		return SettingsSection::where([['company_id', '=', $this->company_id], ['type', '=', ISC_INVOICE_CLIENT_DETAILS_TYPE]])->first();
	}

	/**
	 * fetchCompanyDetailsSettings function
	 *
	 * @return SettingsSection|null
	 */
	public function fetchCompanyDetailsSettings() : SettingsSection|null {
		return SettingsSection::where([['company_id', '=', $this->company_id], ['type', '=', ISC_INVOICE_COMPANY_DETAILS_TYPE]])->first();
	}

	/**
	 * fetchCompanyAddresSettings function
	 *
	 * @return SettingsSection|null
	 */
	public function fetchCompanyAddressSettings() : SettingsSection|null {
		return SettingsSection::where([['company_id', '=', $this->company_id], ['type', '=', ISC_INVOICE_COMPANY_ADDRESS_TYPE]])->first();
	}

	/**
	 * fetchCompanyInvoiceSettings function
	 *
	 * @return SettingsSection|null
	 */
	public function fetchInvoiceSettings() : SettingsSection|null {
		return SettingsSection::where([['company_id', '=', $this->company_id], ['type', '=', ISC_INVOICE_DETAILS_TYPE]])->first();
	}

	/**
	 * fetchTotalFieldsSettings function
	 *
	 * @return SettingsSection|null
	 */
	public function fetchTotalFieldsSettings() : SettingsSection|null {
		return SettingsSection::where([['company_id', '=', $this->company_id], ['type', '=', ISC_INVOICE_TOTAL_FIELDS_TYPE]])->first();
	}

	/**
	 * fetchCustomFieldsOfClient function
	 *
	 * @param integer $client_id
	 * @return Collection|null
	 */
	public function fetchCustomFieldValuesOfClient(int $client_id) : Collection|null {
		return ClientCustomFieldValue::where([['client_id', '=', $client_id]])->get();
	}

}