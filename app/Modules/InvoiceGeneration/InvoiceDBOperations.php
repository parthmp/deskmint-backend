<?php

namespace App\Modules\InvoiceGeneration;

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
	 * @return Collection
	 */
	public function fetchGeneralSettings() : Collection {
		return SettingsSection::where([['company_id', '=', $this->company_id], ['type', '=', ISC_INVOICE_GENERAL_DETAILS_TYPE]])->first();
	}

	/**
	 * fetchInvoiceRow function
	 *
	 * @return Collection
	 */
	public function fetchInvoiceRow() : Collection {
		return Invoice::where('id', '=', $this->invoice_id)->first();
	}
}