<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Exceptions\RecurringInvoiceException;
use App\Services\Invoice\InvoiceSettingsService;

class InvoiceGeneratorForRecurringInvoice {

	private ?array $invoice_data = null;
	private ?int $company_id = null;

	public function __construct(
		private InsertInvoice $insert_invoice,
		private InvoiceSettingsService $invoice_settings_service
	){}

	/**
	 * setInvoiceData function
	 *
	 * @param array $invoice_data
	 * @return self
	 */
	public function setInvoiceData(array $invoice_data) : self {
		$this->invoice_data = $invoice_data;
		return $this;
	}

	/**
	 * setCompany function
	 *
	 * @param integer $company_id
	 * @return self
	 */
	public function setCompany(int $company_id) : self {
		$this->company_id = $company_id;
		return $this;
	}

	/**
	 * insertInvoiceWithData function
	 *
	 * @return self
	 */
	public function insertInvoiceWithData() : self {

		if(!$this->invoice_data || !$this->company_id){
			throw new RecurringInvoiceException('invoice data not provided', 'invoice_data_not_provided', (int) config('global.error_code'));
		}

		$settings = $this->invoice_settings_service->setCompany((int) $this->company_id)->getProductColumns();

		$this->insert_invoice->insertInvoice($this->invoice_data, $settings);

		return $this;

	}

}