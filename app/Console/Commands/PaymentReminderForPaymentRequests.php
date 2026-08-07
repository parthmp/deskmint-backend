<?php

namespace App\Console\Commands;

use App\Enums\PaymentRequests\PaymentRequestStatus;
use App\Jobs\SendPaymentReminderForPaymentRequestJob;
use App\Models\Company;
use App\Models\PaymentRequest;
use App\Traits\EmailSettingsForCommands;
use App\Traits\SettingsDefault;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('deskmint:payment-reminder-requests')]
#[Description('Checks for unpaid payment requests to send payment reminder emails')]
class PaymentReminderForPaymentRequests extends Command
{
	use SettingsDefault, EmailSettingsForCommands;
	
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $companies = Company::select('id')->get();

		$selects = [
			'payment_requests.id as id',
			'payment_requests.uuid as uuid',
			'payment_requests.amount as amount',
			'payment_requests.payment_gateway as payment_gateway',
			'clients.email as client_email',
			'clients.first_name as client_first_name',
			'clients.last_name as client_last_name',
			'currencies.code as currency_code',
		];

		foreach($companies as $company){

			$settings = $this->fetchEmailSettings((int) $company->id, 'email_content_reminder_payment_request');
			
			PaymentRequest::query()
						->select($selects)
						->join('currencies', 'currencies.id', '=', 'payment_requests.currency_id')
						->join('clients', 'clients.id', '=', 'payment_requests.client_id')
						->where([
							['clients.send_reminders', '=', 1],
							['payment_requests.reminders_sent', '<', $settings['reminders']['send_n_times']],
							['payment_requests.company_id', '=', $company->id],
							['payment_requests.hidden_sent_at', '<', now()->subDays((int) $settings['reminders']['days_gap'])]
						])
						->where('payment_requests.status', '=', PaymentRequestStatus::SENT->value)
						->chunkById(200, function($prs) use ($settings){
							
							foreach ($prs as $pr) {
								SendPaymentReminderForPaymentRequestJob::dispatch(
									$pr->id,
									$pr->uuid,
									$settings['content'],
									$pr->client_email,
									$pr->client_first_name,
									$pr->client_last_name,
									$pr->amount,
									$pr->payment_gateway,
									$pr->currency_code
								);
							}
						}, 'payment_requests.id', 'id');
		}
    }
}
