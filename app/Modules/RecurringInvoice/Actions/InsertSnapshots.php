<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Modules\InvoiceGeneration\InvoiceSnapshot;
use App\Modules\RecurringInvoice\DB\DB;
use Illuminate\Database\Eloquent\Collection;

class InsertSnapshots {

	public function __construct(
		private DB $db
	){}

	/**
	 * fetchRequiredData function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @return array
	 */
	private function fetchRequiredData(int $company_id, array $invoice_ids) : array {

		//fetch data here because there are no recurring invoice snapshots to copy from nor the InsertInvoiceDataJob class can know which invoices were inserted.
		$data_from_db['invoice_data'] = $this->db->fetchInvoiceDataForSnapshots($invoice_ids);

		$invoice_and_clients = $this->db->fetchClientAndInvoiceIds($company_id, $invoice_ids);

		$client_ids = array_column($invoice_and_clients, 'client_id');

		$data_from_db['clients_custom_field_values'] = $this->db->fetchClientCustomFieldValuesForSnapshot($client_ids);
		
		$data_from_db['company_additional_fields'] = $this->db->fetchAdditionalCompanyFields($company_id);
		
		//this one is for custom fields that shows up in custom fields tab in the frontend.
		$data_from_db['invoice_custom_field_values'] = $this->db->fetchCustomFieldValuesOfInvoices($invoice_ids) ?? [];
		
		$data_from_db['default_company'] = $this->db->fetchDefaultCompanyById($company_id);

		//this one is for product columns
		$data_from_db['invoice_items'] = $this->db->fetchInvoiceItemsWithCustomCols($company_id, $invoice_ids);
		
		return $data_from_db;

	}

	/**
	 * filterClientCustomFieldValues function
	 *
	 * @param integer $client_id
	 * @param Collection $custom_field_values
	 * @return Collection
	 */
	private function filterClientCustomFieldValues(int $client_id, Collection $custom_field_values): Collection {
		return $custom_field_values->filter(
			fn($custom_field_value) => (int) $client_id === (int) $custom_field_value->client_id
		)->values();
	}


	/**
	 * filterInvoiceData function
	 *
	 * @param integer $invoice_id
	 * @param Collection $values
	 * @return Collection
	 */
	private function filterInvoiceData(int $invoice_id, Collection $values) : Collection {
		return $values->filter(
			fn($entry) => (int) $invoice_id === (int) $entry->invoice_id
		)->values();
	}

	/**
	 * filterSnapshotData function
	 *
	 * @param integer $recurring_invoice_id
	 * @param array $recurring_invoice_data
	 * @return array
	 */
	public function filterSnapshotData(int $recurring_invoice_id, array $recurring_invoice_data) : array {

		foreach($recurring_invoice_data as $row){
			if((int) $recurring_invoice_id === (int) $row['id']){
				return json_decode($row['settings_snapshot'], true);
			}
		}

		return [];

	}


	/**
	 * insertSnapshots function
	 *
	 * @param integer $company_id
	 * @param array $inserted_ids
	 * @return void
	 */
	public function insertSnapshots(int $company_id, array $inserted_ids) : void {

		$insert = [];

		$recurring_invoice_ids = array_column($inserted_ids, 'recurring_invoice_id');
		$recurring_invoice_data = $this->db->fetchRecurringInvoiceData((int) $company_id, $recurring_invoice_ids, ['recurring_invoices.id', 'recurring_invoices.settings_snapshot'], false);
		
		$invoice_ids = array_column($inserted_ids, 'id');
		$data_from_db = $this->fetchRequiredData($company_id, $invoice_ids);

		
		foreach($data_from_db['invoice_data'] as $invoice){

			//none of these create n+1 in loop :)
			$client_custom_field_values = $this->filterClientCustomFieldValues((int) $invoice->client_id, $data_from_db['clients_custom_field_values']);

			//this one is for custom fields that shows up in custom fields tab in the frontend.
			$invoice_custom_field_values = $this->filterInvoiceData((int) $invoice->id, $data_from_db['invoice_custom_field_values']);

			//this one is for product columns
			$invoice_items = $this->filterInvoiceData((int) $invoice->id, $data_from_db['invoice_items']);

			$snapshot_data = $this->filterSnapshotData((int) $invoice->recurring_invoice_id, $recurring_invoice_data);

			
			$snapshot = app(InvoiceSnapshot::class)
						->setCompanyId($company_id, false)
						->setInvoiceId($invoice->id, $invoice, false)
						->setTimezone($invoice->timezone)
						->setLogoSnapsot($snapshot_data['company_logo'])
						->setGeneralSettings($snapshot_data['general_settings'])
						->setClientSnapshot($snapshot_data['client_settings'], $client_custom_field_values)
						->setCompanySnapshot($snapshot_data['company_settings'], $data_from_db['company_additional_fields'])
						->setInvoiceSnapshot($snapshot_data['invoice_settings'], $invoice_custom_field_values, $data_from_db['default_company'], $snapshot_data['product_column_settings'], $invoice_items)
						->setInvoiceRowsSnapshot($snapshot_data['product_column_settings'], $invoice_items)
						->setTotalsSnapshot($snapshot_data['total_field_settings'])
						->setTermsSnapshot()
						->output();

			$insert[] = [
				'invoice_id'	=>	$invoice->id,
				'snapshot'		=>	json_encode($snapshot),
				'created_at'	=>	now(),
				'updated_at'	=> 	now()
			];
		}

		if(!empty($insert)){
			$this->db->insertInvoiceSnapshots($insert);
		}

	}

}