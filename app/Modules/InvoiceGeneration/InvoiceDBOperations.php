<?php

namespace App\Modules\InvoiceGeneration;

use App\Models\AdditionalCompanyField;
use App\Models\ClientCustomFieldValue;
use App\Models\ClientsCustomField;
use App\Models\Invoice;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoiceItem;
use App\Models\SettingsSection;
use Illuminate\Database\Eloquent\Collection;

/**
 * InvoiceDBOperations class
 */
class InvoiceDBOperations{
	
	private int $company_id;
	private int $invoice_id;

	private array $data = [];

	/**
	 * __construct function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 */
	public function __construct(int $company_id, int $invoice_id){
		$this->company_id = $company_id;
		$this->invoice_id = $invoice_id;
		$this->data = $this->fetchRequiredSettings();
	}

	/**
	 * fetchRequiredSettings function
	 *
	 * @return array
	 */
	public function fetchRequiredSettings() : array {

		$settings_data = SettingsSection::where([['company_id', '=', $this->company_id]])->whereIn('type', [
			ISC_INVOICE_GENERAL_DETAILS_TYPE,
			ISC_INVOICE_CLIENT_DETAILS_TYPE,
			ISC_INVOICE_COMPANY_DETAILS_TYPE,
			ISC_PRODUCT_COLUMNS_TYPE,
			ISC_INVOICE_COMPANY_ADDRESS_TYPE,
			ISC_INVOICE_DETAILS_TYPE,
			ISC_INVOICE_TOTAL_FIELDS_TYPE,
			ESC_EMAIL_CONTENT_TYPE,
			PAYMENTS_PAYPAL_TYPE,
			PAYMENTS_STRIPE_TYPE
		])->get()->toArray();

		return array_values($settings_data);
	}

	/**
	 * filterArray function
	 *
	 * @param string $key
	 * @return array|null
	 */
	private function filterArray(string $key) : ?array {
		
		$filtered = array_values(array_filter($this->data, function($item) use($key) {
			return $item['type'] === $key;
		}));
		
		if(!isset($filtered[0])){
			return null;
		}

		return !empty($filtered[0]) ? $filtered[0] : null;
	}

	
	/**
	 * fetchGeneralSettings function
	 *
	 * @return SettingsSection|null
	 */
	public function fetchGeneralSettings() : array|null {
		return $this->filterArray(ISC_INVOICE_GENERAL_DETAILS_TYPE);
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
	 * @return array|null
	 */
	public function fetchClientDetailsSettings() : array|null {
		return $this->filterArray(ISC_INVOICE_CLIENT_DETAILS_TYPE);
	}

	/**
	 * fetchCompanyDetailsSettings function
	 *
	 * @return array|null
	 */
	public function fetchCompanyDetailsSettings() : array|null {
		return $this->filterArray(ISC_INVOICE_COMPANY_DETAILS_TYPE);
	}

	/**
	 * fetchInvoiceProductCoulumnsSettings function
	 *
	 * @return array|null
	 */
	public function fetchInvoiceProductCoulumnsSettings() : array|null {
		return $this->filterArray(ISC_PRODUCT_COLUMNS_TYPE);
	}

	/**
	 * fetchCompanyAddresSettings function
	 *
	 * @return SettingsSection|null
	 */
	public function fetchCompanyAddressSettings() : array|null {
		return $this->filterArray(ISC_INVOICE_COMPANY_ADDRESS_TYPE);
	}

	/**
	 * fetchCompanyInvoiceSettings function
	 *
	 * @return array|null
	 */
	public function fetchInvoiceSettings() : array|null {
		return $this->filterArray(ISC_INVOICE_DETAILS_TYPE);
	}

	/**
	 * fetchTotalFieldsSettings function
	 *
	 * @return array|null
	 */
	public function fetchTotalFieldsSettings() : array|null {
		return $this->filterArray(ISC_INVOICE_TOTAL_FIELDS_TYPE);
	}

	/**
	 * fetchCustomFieldsOfClient function
	 *
	 * @param integer $client_id
	 * @return Collection|null
	 */
	public function fetchCustomFieldValuesOfClient(int $client_id) : Collection|null {
		return ClientCustomFieldValue::where([['client_id', '=', $client_id]])->withTrashed()->get();
	}

	/**
	 * fetchAdditionalCompanyFields function
	 *
	 * @return Collection|null
	 */
	public function fetchAdditionalCompanyFields() : Collection|null{
		return AdditionalCompanyField::where('company_id', '=', $this->company_id)->withTrashed()->get();
	}

	/**
	 * fetchCustomFieldValuesOfInvoice function
	 *
	 * @return Collection|null
	 */
	public function fetchCustomFieldValuesOfInvoice() : Collection|null {
		return InvoiceCustomFieldValue::where([['invoice_id', '=', $this->invoice_id]])->withTrashed()->get();
	}

	/**
	 * fetchInvoiceItems function
	 *
	 * @return Collection|null
	 */
	public function fetchInvoiceItems() : Collection|null {
		return InvoiceItem::where([['invoice_id', '=', $this->invoice_id]])->withTrashed()->with('product')->get();
	}

	/**
	 * fetchEmailContentSettings function
	 *
	 * @return array|null
	 */
	public function fetchEmailContentSettings() : ?array {
		return $this->filterArray(ESC_EMAIL_CONTENT_TYPE);
	}

	/**
	 * fetchPaymentSettings function
	 *
	 * @return array|null
	 */
	public function fetchPaymentSettings(int $payment_method) : ?array {
		return $this->filterArray($payment_method === PAYMENT_PAYPAL ? PAYMENTS_PAYPAL_TYPE : PAYMENTS_STRIPE_TYPE);
	}

}