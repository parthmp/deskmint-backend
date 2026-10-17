<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Exceptions\RecurringInvoiceException;
use App\Services\Invoice\InvoiceSettingsService;
use App\Modules\RecurringInvoice\Actions\InsertInvoice;
use App\Repositories\Invoice\InvoiceRepository;

class ManageActions {

	private ?array $data = null;
	private ?int $company_id = null;
	private array $inserted = [];

	public function __construct(
		private InsertInvoice $insert_invoice,
		private InsertInvoiceItems $insert_invoice_items,
		private InvoiceSettingsService $invoice_settings_service,
		private InsertCustomFields $insert_custom_fields,
		private InvoiceRepository $invoice_repository,
		private InsertSnapshots $insert_snapshots
	){}

	/**
	 * setInvoiceData function
	 *
	 * @param array $invoice_data
	 * @return self
	 */
	public function setInvoiceData(array $invoice_data) : self {
		$this->data = $invoice_data;
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
	 * getInserted function
	 *
	 * @return array
	 */
	public function getInserted() : array {
		return $this->inserted;
	}

	/**
	 * insertInvoiceWithData function
	 *
	 * @return self
	 */
	public function insertInvoiceWithData() : self {

		if(!$this->data['data'] || !$this->company_id || empty($this->data['data'])){
			throw new RecurringInvoiceException('invoice data not provided', 'invoice_data_not_provided', (int) config('global.error_code'));
		}

		$settings = $this->invoice_settings_service->setCompany((int) $this->company_id)->getProductColumns();
		$invoice_numbers = $this->invoice_settings_service->setCompany((int) $this->company_id)->getInvoiceNumbers();

		$inserted = $this->insert_invoice->insertInvoice($this->data['data'], $settings, $invoice_numbers);
		
		$this->inserted = $inserted;

		return $this;

	}

	/**
	 * insertInvoiceItems function
	 *
	 * @return self
	 */
	public function insertInvoiceItems() : self {
		
		if(!$this->data['recurring_invoice_items'] || empty($this->data['recurring_invoice_items']) || empty($this->inserted)){
			throw new RecurringInvoiceException('invoice items not provided', 'invoice_items_not_provided', (int) config('global.error_code'));
		}

		$this->insert_invoice_items->insertInvoiceItems($this->inserted, $this->data['recurring_invoice_items']);

		return $this;

	}

	/**
	 * insertCustomProductColumns function
	 *
	 * @return self
	 */
	public function insertCustomProductColumns() : self {

		if(!$this->data['recurring_invoice_custom_product_columns']){
			throw new RecurringInvoiceException('invoice custom product field columns not provided', 'invoice_cpfc_not_provided', (int) config('global.error_code'));
		}

		$this->insert_invoice_items->insertInvoiceCustomProductColumns($this->inserted, $this->data['recurring_invoice_custom_product_columns']);

		return $this;

	}

	/**
	 * insertCustomFieldValues function
	 *
	 * @return self
	 */
	public function insertCustomFieldValues() : self {

		if(!$this->data['recurring_invoice_custom_field_values'] || !$this->data['recurring_invoice_flat_table_rows']){
			throw new RecurringInvoiceException('invoice custom field values not provided', 'invoice_cfv_not_provided', (int) config('global.error_code'));
		}

		$this->insert_custom_fields->insertCustomFields($this->inserted, $this->data['recurring_invoice_custom_field_values'], $this->data['recurring_invoice_flat_table_rows']);

		return $this;

	}

	
	/**
	 * insertSnapshots function
	 *
	 * @return self
	 */
	public function insertSnapshots() : self {

		$invoice_ids = array_column($this->inserted, 'id');

		if(empty($invoice_ids)){
			logger('empty invoice ids');
			return $this;
		}

		
		$this->insert_snapshots->insertSnapshots($this->company_id, $invoice_ids);

		return $this;

	}

}