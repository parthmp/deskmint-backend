<?php

namespace App\Modules\RecurringInvoice\DB;

use App\Enums\EmailSettings\EmailSettingsContent;
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
		return RecurringInvoice::select(...$selects)->where('company_id', '=', $company_id)->whereIn('id', $recurring_invoice_ids)->orderBy('id', 'asc')->get()->toArray();
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

}
