<?php

namespace App\Modules\InvoiceGeneration;

use App\Models\AdditionalCompanyField;
use App\Models\AdditionalProductColumnsFieldValue;
use App\Models\ClientCustomFieldValue;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoiceItem;
use App\Models\InvoiceSnapshot;
use App\Models\SettingsSection;
use App\Models\User;
use App\Repositories\Company\CompanyRepository;
use App\Traits\SettingsDefault;
use Illuminate\Database\Eloquent\Collection;

/**
 * InvoiceDBOperations class
 */
class InvoiceDBOperations{

	use SettingsDefault;
	
	private int $company_id;
	private int $invoice_id;

	private array $data = [];
	
	/**
	 * setCompanyId function
	 *
	 * @param integer $company_id
	 * @return self
	 */
	public function setCompanyId(int $company_id) : self {
		$this->company_id = $company_id;
		return $this;
	}

	/**
	 * setInvoiceId function
	 *
	 * @param integer $invoice_id
	 * @return self
	 */
	public function setInvoiceId(int $invoice_id) : self {
		$this->invoice_id = $invoice_id;
		return $this;
	}

	/**
	 * execRequiredSettings function
	 *
	 * @return self
	 */
	public function execRequiredSettings() : self {
		$this->data = $this->fetchRequiredSettings();
		return $this;
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
		//return Invoice::where('id', '=', $this->invoice_id)->withTrashed()->first();
		return Invoice::where('id', '=', $this->invoice_id)->withTrashed()->with(['client_wt.billing_country', 'client_wt.currency'])->first();
	}

	/**
	 * fetchInvoiceRowObj function
	 *
	 * @return Invoice|null
	 */
	public function fetchInvoiceRowObj() : ?Invoice {
		return Invoice::where('id', '=', $this->invoice_id)->withTrashed()->with('client_wt')->first();
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
		//return ClientCustomFieldValue::where([['client_id', '=', $client_id]])->withTrashed()->get();
		return ClientCustomFieldValue::where('client_id', $client_id)->withTrashed()->with(['clients_custom_field_wt.custom_field_type_wt'])->get();
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
		//return InvoiceCustomFieldValue::where([['invoice_id', '=', $this->invoice_id]])->withTrashed()->get();
		return InvoiceCustomFieldValue::where([['invoice_id', '=', $this->invoice_id]])->withTrashed()->with(['invoices_custom_field_wt.custom_field_type_wt'])->get();
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
	 * fetchInvoiceItemsWithCustomCols function
	 *
	 * @return Collection
	 */
	public function fetchInvoiceItemsWithCustomCols() : Collection {
		return InvoiceItem::where([['invoice_id', '=', $this->invoice_id]])
								->withTrashed()
								->with(['product', 'custom_field_values.custom_product_field' => function($query){
        								$query->where('company_id', $this->company_id);
    							}])
								->get();
	}

	/**
	 * fetchEmailContentSettings function
	 *
	 * @return array
	 */
	public function fetchEmailContentSettings() : array {

		$email_content = $this->filterArray(ESC_EMAIL_CONTENT_TYPE);
		
		if($email_content){
			return $email_content;
		}
		
		return $this->getDefaultEmailContentSettings();

	}

	/**
	 * fetchPaymentSettings function
	 *
	 * @return array|null
	 */
	public function fetchPaymentSettings(int $payment_method) : ?array {
		return $this->filterArray($payment_method === PAYMENT_PAYPAL ? PAYMENTS_PAYPAL_TYPE : PAYMENTS_STRIPE_TYPE);
	}

	/**
	 * fetchAdminEmails function
	 *
	 * @return null|array
	 */
	public function fetchAdminEmails() : ?array {

		$info = User::select('name', 'email')->get();
		if($info->isEmpty()){
			return null;
		}
		
		return $info->toArray();

	}

	/**
	 * fetchDefaultCompanyById function
	 *
	 * @param integer $company_id
	 * @return Company|null
	 */
	public function fetchDefaultCompanyById(int $company_id) : ?Company {
		return Company::where([['id', '=', $company_id], ['default', '=', 1]])->with('country')->first();
	}

	/**
	 * fetchCustomProductColumns function
	 *
	 * @return Collection
	 */
	public function fetchCustomProductColumns() : Collection {
		return AdditionalProductColumnsFieldValue::where('invoice_id', '=', $this->invoice_id)->whereHas('custom_product_field')->with(['custom_product_field' => function($query){
			$query->where('company_id', $this->company_id);
		}])->get();
	}
	

	/**
	 * fetchInvoiceSnapshot function
	 *
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchInvoiceSnapshot(int $invoice_id) : array {
		$snapshot = InvoiceSnapshot::where('invoice_id', '=', $invoice_id)->first();
		return $snapshot->snapshot;
	}

}