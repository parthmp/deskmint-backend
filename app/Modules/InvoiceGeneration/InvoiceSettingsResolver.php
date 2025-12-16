<?php

namespace App\Modules\InvoiceGeneration;

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
	private InvoiceDBOperations $invoice_db_operations;

	/**
	 * __construct function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 */
	public function __construct(int $company_id, int $invoice_id){
		$this->company_id = $company_id;
		$this->invoice_id = $invoice_id;
		$this->invoice_db_operations = new InvoiceDBOperations($company_id, $invoice_id);
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

		return json_decode($settings->settings_json, true);

	}

}