<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Modules\RecurringInvoice\DB\DB;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class InsertInvoiceDataJob implements ShouldQueue {

	use Queueable, Batchable;

	public function __construct(
		private int $company_id,
		private array $recurring_invoice_ids
	){}

	/**
	 * fetchData function
	 *
	 * @return array
	 */
	private function fetchData() : array {
		//fetch
		$db = app(DB::class);
		$recurring_invoice_data = $db->fetchRecurringInvoiceData($this->company_id, $this->recurring_invoice_ids);
		$recurring_invoice_custom_field_values = $db->fetchRecurringInvoiceCustomFieldValues($this->recurring_invoice_ids);

		return [
			'data'					=>	$recurring_invoice_data,
			'custom_field_values'	=>	$recurring_invoice_custom_field_values
		];

	}


	/**
	 * handle function
	 *
	 * @return void
	 */
	public function handle() : void {

		$data = $this->fetchData();

	}

}