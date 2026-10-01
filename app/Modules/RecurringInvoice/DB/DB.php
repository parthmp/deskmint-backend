<?php

namespace App\Modules\RecurringInvoice\DB;

use App\Enums\EmailSettings\EmailSettingsContent;
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
	public function fetchData(int $company_id, array $recurring_invoice_ids, array $selects = ['*']) : array {
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
