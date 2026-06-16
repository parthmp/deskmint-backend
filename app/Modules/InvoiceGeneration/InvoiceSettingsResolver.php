<?php

namespace App\Modules\InvoiceGeneration;

use App\Models\Invoice;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Illuminate\Database\Eloquent\Collection;

/**
 * InvoiceSettingsResolver class
 */
class InvoiceSettingsResolver{

	use SettingsDefault;

	private int $company_id;
	private int $invoice_id;
	
	/**
	 * __construct function
	 *
	 * @param InvoiceDBOperations $invoice_db_operations
	 */
	public function __construct(private InvoiceDBOperations $invoice_db_operations){
		
	}

	/**
	 * setCompanyId function
	 *
	 * @param integer $company_id
	 * @return self
	 */
	public function setCompanyId(int $company_id) : self {
		$this->company_id = $company_id;
		$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($company_id);
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
		$this->invoice_db_operations = $this->invoice_db_operations->setInvoiceId($invoice_id)->execRequiredSettings();
		return $this;
	}
	
	/**
	 * fetchGeneral function
	 *
	 * @return array
	 */
	public function fetchGeneral() : array {

		$settings = $this->invoice_db_operations->fetchGeneralSettings();
		
		if(!$settings){
			return $this->getDefaultInvoiceGeneralSettings();
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchClientDetails function
	 *
	 * @return array
	 */
	public function fetchClientDetails() : array {

		$settings = $this->invoice_db_operations->fetchClientDetailsSettings();

		if(!$settings){
			return $this->getDefaultInvoiceClientDetailsSettings();
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchCompanyDetails function
	 *
	 * @return array
	 */
	public function fetchCompanyDetails() : array {

		$settings = $this->invoice_db_operations->fetchCompanyDetailsSettings();

		if(!$settings){
			$default = $this->getDefaultInvoiceCompanyDetailsSettings($this->company_id);
			return $default['rows'];
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchCompanyAddressDetails function
	 *
	 * @return array
	 */
	public function fetchCompanyAddressDetails() : array {

		$settings = $this->invoice_db_operations->fetchCompanyAddressSettings();

		if(!$settings){
			$default = $this->getDefaultInvoiceCompanyAddressSettings($this->company_id);
			return $default['rows'];
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchCompanyInvoiceDetails function
	 *
	 * @return array
	 */
	public function fetchInvoiceDetails() : array {

		$settings = $this->invoice_db_operations->fetchInvoiceSettings();

		if(!$settings){
			$default = $this->getDefaultInvoiceDetailsSettings($this->company_id);
			return $default['rows'];
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchTotalFieldsDetails function
	 *
	 * @return array
	 */
	public function fetchTotalFieldsDetails() : array {

		$settings = $this->invoice_db_operations->fetchTotalFieldsSettings();

		if(!$settings){
			$default = $this->getDefaultTotalFieldsSettings();
			return $default['rows'];
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchProductRowsSettings function
	 *
	 * @param Invoice $invoice
	 * @return array
	 */
	public function fetchProductRowsSettings(Invoice $invoice) : array {

		$settings_snapshot = trim($invoice->settings_snapshot);

		if($settings_snapshot !== ''){
			return json_decode($settings_snapshot, true);
		}

		$settings_section = $this->invoice_db_operations->fetchInvoiceProductCoulumnsSettings();

		if($settings_section){
			return json_decode($settings_section['settings_json'], true);
		}

		return $this->getDefaultProductColumnsSettings((int) $this->company_id);

	}

}