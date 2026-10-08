<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Modules\RecurringInvoice\DB\DB;
use App\Modules\RecurringInvoice\Implementation\SendEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendRecurringInvoiceJob implements ShouldQueue
{
    use Queueable;
	
	public function __construct(
		private array $recurring_invoice_ids,
		private int $company_id,
	){}


	/**
	 * handle function
	 *
	 * @return void
	 */
	public function handle() : void {

		$selects = ['clients.email', 'clients.first_name', 'clients.last_name', 'recurring_invoices.total', 'recurring_invoices.frequency', 'recurring_invoices.custom_frequency_days', 'recurring_invoices.uuid', 'currencies.code as currency', 'recurring_invoices.id', 'recurring_invoices.status', 'recurring_invoices.payment_gateway'];
		$db = app(DB::class);
		$client_data = $db->fetchDataForEmailSending($this->company_id, $this->recurring_invoice_ids, $selects);
		$content = $db->fetchEmailContent($this->company_id);

		$send_email = app(SendEmail::class);
		$send_email->sendEmail($client_data, $this->company_id, $content['recurring_invoice_email_content']);

	}

}