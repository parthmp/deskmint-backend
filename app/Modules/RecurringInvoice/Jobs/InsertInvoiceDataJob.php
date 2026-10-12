<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Modules\RecurringInvoice\Actions\InvoiceGeneratorForRecurringInvoice;
use App\Modules\RecurringInvoice\DB\DB;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB as FacadeDB;

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
		$recurring_invoice_data = $db->fetchRecurringInvoiceData($this->company_id, $this->recurring_invoice_ids, ['recurring_invoices.*', 'clients.first_name', 'clients.last_name', 'clients.client_company_name']);
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

		$invoice_generator = app(InvoiceGeneratorForRecurringInvoice::class);

		FacadeDB::transaction(function () use ($invoice_generator) {

			$data = $this->fetchData();
			$invoice_generator->setCompany($this->company_id)->setInvoiceData($data['data'])->insertInvoiceWithData();

		});
		

	}

}