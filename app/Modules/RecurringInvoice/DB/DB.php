<?php

namespace App\Modules\RecurringInvoice\DB;

use App\Enums\EmailSettings\EmailSettingsContent;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceCustomFieldValue;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Illuminate\Support\Facades\DB as FacadesDB;

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

}
