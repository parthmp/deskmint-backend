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
	 * insertSnapshots function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @return void
	 */
	public function insertSnapshots(int $company_id, array $invoice_ids) : void {

		$snapshot_data = $this->fetchRequiredData($company_id, $invoice_ids);
		
		foreach($snapshot_data['invoice_data'] as $invoice){

			$client_custom_field_values = $this->filterClientCustomFieldValues((int) $invoice->client_id, $snapshot_data['clients_custom_field_values']);

			$snapshot = app(InvoiceSnapshot::class)
						->setCompanyId($company_id, false)
						->setInvoiceId($invoice->id, $invoice, false)
						->setTimezone($invoice->timezone)
						->setLogoSnapsot($snapshot_data['company_logo'])
						->setGeneralSettings($snapshot_data['general_settings'])
						->setClientSnapshot($snapshot_data['client_settings'], $client_custom_field_values)
						->setCompanySnapshot($snapshot_data['company_settings'], $snapshot_data['company_additional_fields'])
						// ->setInvoiceSnapshot()
						// ->setInvoiceRowsSnapshot()
						// ->setTotalsSnapshot()
						// ->setTermsSnapshot()
						->output();

			logger($snapshot);
			throw new Exception("stop");
		}

	}

}