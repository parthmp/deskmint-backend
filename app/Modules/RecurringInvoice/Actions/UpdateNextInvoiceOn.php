<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Enums\RecurringInvoices\Frequencies;
use App\Modules\RecurringInvoice\DB\DB;
use Carbon\Carbon;

class UpdateNextInvoiceOn {
	
	public function __construct(
		private DB $db
	){}

	/**
	 * dateCalculation function
	 *
	 * @param Carbon $next_invoice_on
	 * @param integer $frequency
	 * @param integer $custom_days
	 * @return Carbon
	 */
	private function dateCalculation(Carbon $next_invoice_on, int $frequency, int $custom_days) : Carbon {

		$date = $next_invoice_on;

		switch($frequency){
			case Frequencies::DAILY->value:
				$date = $date->clone()->addDay();
				break;
			case Frequencies::WEEKLY->value:
				$date = $date->clone()->addWeek();
				break;
			case Frequencies::BI_WEEKLY->value:
				$date = $date->clone()->addWeeks(2);
				break;
			case Frequencies::MONTHLY->value:
				$date = $date->clone()->addMonthNoOverflow();
				break;
			case Frequencies::THREE_MONTHS->value:
				$date = $date->clone()->addMonthsNoOverflow(3);
				break;
			case Frequencies::SIX_MONTHS->value:
				$date = $date->clone()->addMonthsNoOverflow(6);
				break;
			case Frequencies::YEARLY->value:
				$date = $date->clone()->addYearNoOverflow();
				break;
			case Frequencies::CUSTOM->value:
				$date = $date->clone()->addDays(max(1, $custom_days));
				break;
		}


		return $date;

	}

	/**
	 * calculateNextInvoiceOn function
	 *
	 * @param array $recurring_invoices
	 * @return array
	 */
	private function calculateNextInvoiceOn(array $recurring_invoices) : array {

		foreach($recurring_invoices as $key => $entry){

			$date = Carbon::parse($entry['next_invoice_on'], 'UTC');

			$date = $this->dateCalculation($date, (int) $entry['frequency'], (int) $entry['custom_frequency_days']);

			$now = Carbon::now('UTC');

			if($date->lt($now)){
				$date = $this->dateCalculation($now, (int) $entry['frequency'], (int) $entry['custom_frequency_days']);
			}

			$recurring_invoices[$key]['next_invoice_on'] = $date->format('Y-m-d H:i:s');
		}

		return $recurring_invoices;
	}
	
	/**
	 * updateNextInvoiceOn function
	 *
	 * @param integer $company_id
	 * @param array $inserted
	 * @return void
	 */
	public function updateNextInvoiceOn(int $company_id, array $inserted) : void {

		$recurring_invoice_ids = array_column($inserted, 'recurring_invoice_id');

		$recurring_invoices = $this->db->fetchOfflineRecurringInvoiceData($company_id, $recurring_invoice_ids, [
			'id', 'frequency', 'custom_frequency_days', 'next_invoice_on'
		]);

		$recurring_invoices = $this->calculateNextInvoiceOn($recurring_invoices);
		$this->db->updateNextInvoiceOn($recurring_invoices);

	}

}