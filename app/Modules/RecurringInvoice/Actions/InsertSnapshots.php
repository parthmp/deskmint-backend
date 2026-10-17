<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Models\ClientCustomFieldValue;
use App\Modules\InvoiceGeneration\InvoiceSnapshot;
use App\Modules\RecurringInvoice\DB\DB;
use App\Repositories\Invoice\InvoiceRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class InsertSnapshots {

	public function __construct(
		private DB $db,
		private InvoiceRepository $invoice_repository
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
		$snapshot_data['company_logo'] = $this->invoice_repository->fetchLogoImage($company_id);
		$snapshot_data['invoice_data'] = $this->db->fetchInvoiceDataForSnapshots($invoice_ids);

		$all_settings = $this->db->fetchSettings($company_id);

		$snapshot_data['general_settings'] = $this->db->fetchGeneralSettings($all_settings);
		$snapshot_data['client_settings'] = $this->db->fetchClientDetails($all_settings);

		$invoice_and_clients = $this->db->fetchClientAndInvoiceIds($company_id, $invoice_ids);

		$client_ids = array_column($invoice_and_clients, 'client_id');

		$snapshot_data['clients_custom_field_values'] = $this->db->fetchClientCustomFieldValuesForSnapshot($client_ids);
		$snapshot_data['company_settings'] = $this->db->fetchCompanyDetails($company_id, $all_settings);
		$snapshot_data['company_additional_fields'] = $this->db->fetchAdditionalCompanyFields($company_id);
		
		$snapshot_data['invoice_custom_field_values'] = $this->db->fetchCustomFieldValuesOfInvoices($invoice_ids) ?? [];
		$snapshot_data['invoice_settings'] = $this->db->fetchInvoiceDetails($company_id, $all_settings);

		$snapshot_data['default_company'] = $this->db->fetchDefaultCompanyById($company_id);

		$snapshot_data['invoice_items'] = $this->db->fetchInvoiceItemsWithCustomCols($company_id, $invoice_ids);

		$snapshot_data['total_field_settings'] = $this->db->fetchTotalFieldsDetails($all_settings);

		$snapshot_data['all_settings'] = $all_settings;

		
		return $snapshot_data;

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
	 * insertSnapshots function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @return void
	 */
	public function insertSnapshots(int $company_id, array $invoice_ids) : void {

		$insert = [];

		$snapshot_data = $this->fetchRequiredData($company_id, $invoice_ids);
		
		foreach($snapshot_data['invoice_data'] as $invoice){

			//none of these create n+1 in loop :)
			$client_custom_field_values = $this->filterClientCustomFieldValues((int) $invoice->client_id, $snapshot_data['clients_custom_field_values']);
			$invoice_custom_field_values = $this->filterInvoiceData((int) $invoice->id, $snapshot_data['invoice_custom_field_values']);
			$product_rows_settings = $this->db->fetchProductRowsSettings($invoice, $snapshot_data['all_settings']);
			$invoice_items = $this->filterInvoiceData((int) $invoice->id, $snapshot_data['invoice_items']);


			$snapshot = app(InvoiceSnapshot::class)
						->setCompanyId($company_id, false)
						->setInvoiceId($invoice->id, $invoice, false)
						->setTimezone($invoice->timezone)
						->setLogoSnapsot($snapshot_data['company_logo'])
						->setGeneralSettings($snapshot_data['general_settings'])
						->setClientSnapshot($snapshot_data['client_settings'], $client_custom_field_values)
						->setCompanySnapshot($snapshot_data['company_settings'], $snapshot_data['company_additional_fields'])
						->setInvoiceSnapshot($snapshot_data['invoice_settings'], $invoice_custom_field_values, $snapshot_data['default_company'], $product_rows_settings, $invoice_items)
						->setInvoiceRowsSnapshot($product_rows_settings, $invoice_items)
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