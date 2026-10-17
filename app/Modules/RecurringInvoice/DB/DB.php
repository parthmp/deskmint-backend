<?php

namespace App\Modules\RecurringInvoice\DB;

use App\Enums\EmailSettings\EmailSettingsContent;
use App\Models\AdditionalCompanyField;
use App\Models\AdditionalProductColumnsFieldValue;
use App\Models\AdditionalProductColumnsFieldValueRi;
use App\Models\ClientCustomFieldValue;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoiceItem;
use App\Models\InvoiceSnapshot;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceCustomFieldValue;
use App\Models\RecurringInvoiceItem;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Schema;

class DB {

	use SettingsDefault;
	
	/**
	 * fetchClientsData function
	 *
	 * @param integer $company_id
	 * @param array $recurring_invoice_ids
	 * @param array $selects
	 * @return array
	 */
	public function fetchDataForEmailSending(int $company_id, array $recurring_invoice_ids, array $selects = ['*']) : array {
		return FacadesDB::table('recurring_invoices')
							->join('clients', 'clients.id', '=', 'recurring_invoices.client_id')
							->join('currencies', 'currencies.id', '=', 'recurring_invoices.currency_id')
							->where('recurring_invoices.company_id', '=', $company_id)
							->whereIn('recurring_invoices.id', $recurring_invoice_ids)
							->select(...$selects)
							->get()
							->map(fn ($item) => (array) $item)
                    		->toArray();
	}

	/**
	 * fetchRecurringInvoiceData function
	 *
	 * @param integer $company_id
	 * @param array $recurring_invoice_ids
	 * @param array $selects
	 * @return array
	 */
	public function fetchRecurringInvoiceData(int $company_id, array $recurring_invoice_ids, array $selects = ['*']) : array {
		return RecurringInvoice::select(...$selects)->join('clients', 'clients.id', '=', 'recurring_invoices.client_id')->where('recurring_invoices.company_id', '=', $company_id)->whereIn('recurring_invoices.id', $recurring_invoice_ids)->orderBy('recurring_invoices.id', 'asc')->get()->toArray();
	}

	/**
	 * fetchRecurringInvoiceCustomFieldValues function
	 *
	 * @param array $recurring_invoice_ids
	 * @param array $selects
	 * @return array
	 */
	public function fetchRecurringInvoiceCustomFieldValues(array $recurring_invoice_ids, array $selects = ['*']) : array {
		return RecurringInvoiceCustomFieldValue::select(...$selects)->whereIn('recurring_invoice_id', $recurring_invoice_ids)->orderBy('recurring_invoice_id', 'asc')->get()->toArray();
	}

	/**
	 * fetchEmailContent function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchEmailContent(int $company_id) : array {

		$content = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', EmailSettingsContent::RECURRING_INVOICES->value]])->first();

		if($content){
			return json_decode($content->settings_json, true);
		}

		return $this->getDefaultRecurringInvoiceEmailContentSettings();

	}

	/**
	 * fetchInvoicesData function
	 *
	 * @param array $uuids
	 * @param array $selects
	 * @return array
	 */
	private function fetchInvoicesData(array $uuids, array $selects = ['*']) : array {
		return Invoice::select(...$selects)->whereIn('uuid', $uuids)->get()->toArray();
	}

	/**
	 * insertMultipleInvoices function
	 *
	 * @param array $invoices
	 * @return array
	 */
	public function insertMultipleInvoices(array $invoices) : array{
		
		Invoice::insert($invoices);

		//remove manual reset
		//TODO: remove this early to avoid a bug.
		if(isset($invoices[0]['company_id'])){
			$manual_reset = $this->fetchManualReset((int) $invoices[0]['company_id']);
			if($manual_reset){
				$json = json_decode($manual_reset->settings_json, true);
				$json['reset'] = 0;
				$json = json_encode($json);
				$manual_reset->settings_json = $json;
				$manual_reset->save();
			}
		}
		


		$uuids = array_column($invoices, 'uuid');
		return $this->fetchInvoicesData($uuids, ['id', 'recurring_invoice_id']);

	}

	/**
	 * fetchManualReset function
	 *
	 * @param integer $company_id
	 * @return SettingsSection|null
	 */
	public function fetchManualReset(int $company_id) : ?SettingsSection {
		return SettingsSection::where([['company_id', '=', $company_id], ['type', '=', ISC_INVOICE_NUMBER_RESET_TYPE]])->first();
	}

	/**
	 * fetchRecurringInvoiceItems function
	 *
	 * @param array $recurring_invoice_ids
	 * @param array $selects
	 * @return array
	 */
	public function fetchRecurringInvoiceItems(array $recurring_invoice_ids, array $selects = ['*']) : array {
		return RecurringInvoiceItem::select(...$selects)->whereIn('recurring_invoice_id', $recurring_invoice_ids)->get()->toArray();
	}

	/**
	 * insertInvoiceItems function
	 *
	 * @param array $items
	 * @return void
	 */
	public function insertInvoiceItems(array $items) : void {
		InvoiceItem::insert($items);
	}
	
	/**
	 * fetchCustomProductColumns function
	 *
	 * @param array $recurring_invoice_ids
	 * @param array $selects
	 * @return array
	 */
	public function fetchCustomProductColumns(array $recurring_invoice_ids, array $selects = ['*']) : array {
		return AdditionalProductColumnsFieldValueRi::select(...$selects)->whereIn('recurring_invoice_id', $recurring_invoice_ids)->get()->toArray();
	}

	/**
	 * insertCustomProductColumns function
	 *
	 * @param array $entries
	 * @return void
	 */
	public function insertCustomProductColumns(array $entries) : void {
		AdditionalProductColumnsFieldValue::insert($entries);
	}

	/**
	 * fetchCustomFieldValues function
	 *
	 * @param array $recurring_invoice_ids
	 * @param array $selects
	 * @return array
	 */
	public function fetchCustomFieldValues(array $recurring_invoice_ids, array $selects = ['*']) : array {

		return RecurringInvoiceCustomFieldValue::select(...$selects)->whereIn('recurring_invoice_id', $recurring_invoice_ids)->get()->toArray();

	}

	/**
	 * insertCustomFieldValues function
	 *
	 * @param array $rows
	 * @return void
	 */
	public function insertCustomFieldValues(array $rows) : void {
		InvoiceCustomFieldValue::insert($rows);
	}

	/**
	 * fetchFlatTableRows function
	 *
	 * @param array $recurring_invoice_ids
	 * @param array $selects
	 * @return array
	 */
	public function fetchFlatTableRows(array $recurring_invoice_ids, array $selects = ['*']) : array {

		$table = 'recurring_invoices_flat';

		if(!Schema::hasTable($table)){
			return [];
		}

		return FacadesDB::table($table)->select(...$selects)->whereIn('recurring_invoice_id', $recurring_invoice_ids)->get()->map(fn ($row) => (array) $row)->toArray();

	}

	/**
	 * insertFlatTableData function
	 *
	 * @param array $rows
	 * @return void
	 */
	public function insertFlatTableData(array $rows) : void {

		$table = 'invoices_flat';

		if(Schema::hasTable($table)){
			FacadesDB::table($table)->insert($rows);
		}

	}

	/**
	 * fetchInvoiceDataForSnapshots function
	 *
	 * @param array $invoice_ids
	 * @return Collection
	 */
	public function fetchInvoiceDataForSnapshots(array $invoice_ids, $selects = ['*']) : Collection {
		return Invoice::select(...$selects)->whereIn('id', $invoice_ids)->with('client_wt', 'currency', 'client_wt.billing_country', 'company_wt', 'client_wt.currency')->withTrashed()->get();
	}

	/**
	 * fetchSettings function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchSettings(int $company_id) : array {
		
		$settings_data = SettingsSection::where([['company_id', '=', $company_id]])->whereIn('type', [
			ISC_INVOICE_GENERAL_DETAILS_TYPE,
			ISC_INVOICE_CLIENT_DETAILS_TYPE,
			ISC_INVOICE_COMPANY_DETAILS_TYPE,
			ISC_PRODUCT_COLUMNS_TYPE,
			ISC_INVOICE_COMPANY_ADDRESS_TYPE,
			ISC_INVOICE_DETAILS_TYPE,
			ISC_INVOICE_TOTAL_FIELDS_TYPE,
			PAYMENTS_PAYPAL_TYPE,
			PAYMENTS_STRIPE_TYPE,
			EmailSettingsContent::INVOICES->value
		])->get()->toArray();
		
		return array_values($settings_data);
	}

	/**
	 * filterArray function
	 *
	 * @param string $key
	 * @param array $data
	 * @return array|null
	 */
	private function filterArray(string $key, array $data) : ?array {
		
		$filtered = array_values(array_filter($data, function($item) use($key) {
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
	 * @param array $data
	 * @return array|null
	 */
	public function fetchGeneralSettings(array $data) : array|null {

		$settings = $this->filterArray(ISC_INVOICE_GENERAL_DETAILS_TYPE, $data);
		if(!$settings){
			return $this->getDefaultInvoiceGeneralSettings();
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchClientDetails function
	 *
	 * @param array $data
	 * @return array
	 */
	public function fetchClientDetails(array $data) : array {

		$settings = $this->filterArray(ISC_INVOICE_CLIENT_DETAILS_TYPE, $data);

		if(!$settings){
			return $this->getDefaultInvoiceClientDetailsSettings();
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchCompanyDetails function
	 *
	 * @param integer $company_id
	 * @param array $data
	 * @return array
	 */
	public function fetchCompanyDetails(int $company_id, array $data) : array {
		$settings = $this->filterArray(ISC_INVOICE_COMPANY_DETAILS_TYPE, $data);
		
		if(!$settings){
			$default = $this->getDefaultInvoiceCompanyDetailsSettings($company_id);
			return $default['rows'];
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchInvoiceDetails function
	 *
	 * @param integer $company_id
	 * @param array $data
	 * @return array
	 */
	public function fetchInvoiceDetails(int $company_id, array $data) : array {

		$settings = $this->filterArray(ISC_INVOICE_DETAILS_TYPE, $data);
		
		if(!$settings){
			$default = $this->getDefaultInvoiceDetailsSettings($company_id);
			return $default['rows'];
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchProductRowsSettings function
	 *
	 * @param Invoice $invoice
	 * @param array $data
	 * @return array
	 */
	public function fetchProductRowsSettings(Invoice $invoice, array $data) : array {

		$settings_snapshot = trim($invoice->settings_snapshot);

		if($settings_snapshot !== ''){
			return json_decode($settings_snapshot, true);
		}

		$settings = $this->filterArray(ISC_PRODUCT_COLUMNS_TYPE, $data);

		if($settings){
			return json_decode($settings['settings_json'], true);
		}

		return $this->getDefaultProductColumnsSettings((int) $invoice->company_id);

	}
	
	/**
	 * fetchTotalFieldsDetails function
	 *
	 * @param array $data
	 * @return array
	 */
	public function fetchTotalFieldsDetails(array $data) : array {

		$settings = $this->filterArray(ISC_INVOICE_TOTAL_FIELDS_TYPE, $data);

		if(!$settings){
			$default = $this->getDefaultTotalFieldsSettings();
			return $default['rows'];
		}

		return json_decode($settings['settings_json'], true);

	}

	/**
	 * fetchClientCustomFieldValuesForSnapshot function
	 *
	 * @param array $client_ids
	 * @return Collection
	 */
	public function fetchClientCustomFieldValuesForSnapshot(array $client_ids) : Collection {
		
		$data = ClientCustomFieldValue::whereIn('client_id', $client_ids)->withTrashed()->with(['clients_custom_field_wt.custom_field_type_wt'])->get();
		
		return $data;

	}
	
	/**
	 * fetchClientAndInvoiceIds function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @return array
	 */
	public function fetchClientAndInvoiceIds(int $company_id, array $invoice_ids) : array {
		return Invoice::select('id', 'client_id')->where('company_id', '=', $company_id)->whereIn('id', $invoice_ids)->get()->pluck('id', 'client_id')->toArray();
	}

	/**
	 * fetchAdditionalCompanyFields function
	 *
	 * @param integer $company_id
	 * @return Collection|null
	 */
	public function fetchAdditionalCompanyFields(int $company_id) : ?Collection {
		return AdditionalCompanyField::where('company_id', '=', $company_id)->withTrashed()->get();
	}

	/**
	 * fetchCustomFieldValuesOfInvoices function
	 *
	 * @param array $invoice_ids
	 * @return Collection|null
	 */
	public function fetchCustomFieldValuesOfInvoices(array $invoice_ids) : ?Collection {
		return InvoiceCustomFieldValue::whereIn('invoice_id', $invoice_ids)->withTrashed()->with(['invoices_custom_field_wt.custom_field_type_wt'])->get();
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
	 * fetchInvoiceItemsWithCustomCols function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @return Collection
	 */
	public function fetchInvoiceItemsWithCustomCols(int $company_id, array $invoice_ids) : Collection {
		return InvoiceItem::whereIn('invoice_id', $invoice_ids)->withTrashed()->with(['product', 'custom_field_values.custom_product_field' => function($query) use ($company_id){
        								$query->where('company_id', $company_id);
    								}])->get();
	
	}

	/**
	 * insertInvoiceSnapshots function
	 *
	 * @param array $snapshots
	 * @param integer $chunk_size
	 * @return void
	 */
	public function insertInvoiceSnapshots(array $snapshots, int $chunk_size = 50) : void {

		$chunks = array_chunk($snapshots, $chunk_size);

		foreach($chunks as $chunk){
			InvoiceSnapshot::insert($chunk);
		}

	}


}
