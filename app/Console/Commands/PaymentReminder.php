<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentReminderJob;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\SettingsSection;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Traits\EmailSettingsForCommands;
use App\Traits\SettingsDefault;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('deskmint:payment-reminder')]
#[Description('Checks for pending invoices to send payment reminder emails with invoice attached')]
class PaymentReminder extends Command
{
	use SettingsDefault, EmailSettingsForCommands;

    /**
     * Execute the console command.
     */
    public function handle()
    {
		
		$companies = Company::select('id')->get();

		$selects = [
			'invoices.id',
			'clients.email as client_email',
			'clients.first_name as client_first_name',
			'clients.last_name as client_last_name',
			'currencies.code as currency_code',
		];

		foreach($companies as $company){

			$settings = $this->fetchEmailSettings((int) $company->id, 'email_content_reminder');
			
			Invoice::query()
						->select($selects)
						->join('currencies', 'currencies.id', '=', 'invoices.currency_id')
						->join('clients', 'clients.id', '=', 'invoices.client_id')
						->where([
							['clients.send_reminders', '=', 1],
							['invoices.reminders_sent', '<', $settings['reminders']['send_n_times']],
							['invoices.company_id', '=', $company->id],
							['invoices.last_reminder_sent_at', '<', now()->subDays((int) $settings['reminders']['days_gap'])]
						])
						->where(function($q){
								$q->where('invoices.status', '=', InvoiceStatus::PARTIALLY_PAID->value)
								->orWhere('invoices.status', '=', InvoiceStatus::SENT->value);
						})
						->chunkById(200, function($invoices) use ($settings){
							foreach ($invoices as $invoice) {
								SendPaymentReminderJob::dispatch($invoice->id, $settings['content'], $invoice->client_email, $invoice->client_first_name, $invoice->client_last_name, $invoice->currency_code);
							}
						}, 'invoices.id', 'id');

		}


		
    }
}
