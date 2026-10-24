<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Exceptions\RecurringInvoiceException;
use App\Services\Invoice\InvoiceSettingsService;
use App\Modules\RecurringInvoice\Actions\InsertInvoice;
use App\Repositories\Invoice\InvoiceRepository;
use Illuminate\Support\Facades\Redis;

class ManageActions {

	private ?array $data = null;
	private ?int $company_id = null;
	private array $inserted = [];

	public function __construct(
		private InsertInvoice $insert_invoice,
		private InsertInvoiceItems $insert_invoice_items,
		private InvoiceSettingsService $invoice_settings_service,
		private InsertCustomFields $insert_custom_fields,
		private InsertSnapshots $insert_snapshots,
		private InsertPayments $insert_payments
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
	 * hasNoInsertedInvoices function
	 *
	 * @param string $text
	 * @return boolean
	 */
	private function hasNoInsertedInvoices(string $text): bool {
		
		if(empty($this->inserted)){
			logger('no invoices were inserted, '.$text.' did not run');
			return true;
		}

		return false;

	}

	/**
	 * insertInvoiceWithData function
	 *
	 * @param array|null $online_transaction_data
	 * @return self
	 */
	public function insertInvoiceWithData(?array $online_transaction_data = null) : self {

		if(!array_key_exists('data', $this->data) || !$this->company_id){
			throw new RecurringInvoiceException('invoice data not provided', 'invoice_data_not_provided', (int) config('global.error_code'));
		}

		if(empty($this->data['data'])){
			logger('data is empty');
			return $this;
		}

		//lock
		Redis::setex(config('global.invoice_lock_key'), (int) config('global.invoice_lock_seconds'), true);
		
		$invoice_numbers = $this->invoice_settings_service->setCompany((int) $this->company_id)->getInvoiceNumbers();

		$inserted = $this->insert_invoice->insertInvoice($this->data['data'], $invoice_numbers, $online_transaction_data);

		$this->inserted = $inserted;

		//release the lock.
		Redis::del(config('global.invoice_lock_key'));

		return $this;

	}

	/**
	 * insertInvoiceItems function
	 *
	 * @return self
	 */
	public function insertInvoiceItems() : self {

		if($this->hasNoInsertedInvoices('insertInvoiceItems')){
			return $this;
		}
		
		if(!array_key_exists('recurring_invoice_items', $this->data) || empty($this->data['recurring_invoice_items'])){
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

		if($this->hasNoInsertedInvoices('insertCustomProductColumns')){
			return $this;
		}

		if(!array_key_exists('recurring_invoice_custom_product_columns', $this->data)){
			throw new RecurringInvoiceException('invoice custom product field columns not provided', 'invoice_cpfc_not_provided', (int) config('global.error_code'));
		}

		if(empty($this->data['recurring_invoice_custom_product_columns'])){
			logger('no recurring_invoice_custom_product_columns entries were provided');
			return $this;
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

		if($this->hasNoInsertedInvoices('insertCustomFieldValues')){
			return $this;
		}

		if(!array_key_exists('recurring_invoice_custom_field_values', $this->data) || !array_key_exists('recurring_invoice_flat_table_rows', $this->data)){
			throw new RecurringInvoiceException('invoice custom field values not provided', 'invoice_cfv_not_provided', (int) config('global.error_code'));
		}

		if(empty($this->data['recurring_invoice_custom_field_values'])){
			logger('no recurring_invoice_custom_field_values entries were provided');
			return $this;
		}

		if(empty($this->data['recurring_invoice_flat_table_rows'])){
			logger('no recurring_invoice_flat_table_rows entries were provided');
			return $this;
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

		if($this->hasNoInsertedInvoices('insertSnapshots')){
			return $this;
		}
		
		$this->insert_snapshots->insertSnapshots($this->company_id, $this->inserted);

		return $this;

	}

	/**
	 * insertPayments function
	 *
	 * @param array|null $online_transaction_data
	 * @return self
	 */
	public function insertPayments(?array $online_transaction_data = null) : self {

		/**
		 * $online_transaction_data should have
		 * array = [
		 *     [
		 *         'recurring_invoice_id'  => 1,
		 *         'transaction_id'        => 2
		 *     ],
		 *     [
		 *         'recurring_invoice_id'  => 3,
		 *         'transaction_id'        => 4
		 *     ],
		 * ];
		 */

		if($this->hasNoInsertedInvoices('insertPayments')){
			return $this;
		}
		

		if(!$this->company_id || empty($this->data['data'])){
			return $this;
		}

		$this->insert_payments->insertPayments($this->data['data'], $this->inserted, $online_transaction_data);



		return $this;

	}

}