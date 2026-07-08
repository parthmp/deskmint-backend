<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentReminderJob;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\SettingsSection;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Traits\SettingsDefault;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('deskmint:payment-reminder')]
#[Description('Checks for pending invoices to send payment reminder emails with invoice attached')]
class PaymentReminder extends Command
{
	use SettingsDefault;

	/**
	 * fetchEmailSettings function
	 *
	 * @return array
	 */
	private function fetchEmailSettings(int $company_id) : array {

		$reminds_default = $this->getDefaultEmailRemindersSettings();
		$content_default = $this->getDefaultEmailContentSettings();

		$fetched_settings = SettingsSection::whereIn('type', [ESC_EMAIL_REMINDERS_TYPE, ESC_EMAIL_CONTENT_TYPE])->where('company_id', '=', $company_id)->get()->toArray();

		$settings['reminders'] = [
			'days_gap'		=>	(int) $reminds_default['days_gap'],
			'send_n_times'	=>	(int) $reminds_default['send_n_times'],
		];

		$settings['content'] = $content_default['email_content_reminder'];

		foreach($fetched_settings as $temp){

			if(isset($temp['settings_json'])){

				$json = json_decode($temp['settings_json'], true);

				if($temp['type'] === ESC_EMAIL_REMINDERS_TYPE){
					$settings['reminders']['days_gap'] = (int) $json['days_gap'];
					$settings['reminders']['send_n_times'] =  (int) $json['send_n_times'];
				}else if($temp['type'] === ESC_EMAIL_CONTENT_TYPE){
					$settings['content'] = $json['email_content_reminder'];
				}

			}

		}

		return $settings;

	}

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

			$settings = $this->fetchEmailSettings((int) $company->id);
			
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
								->orWhere('invoices.status', '=', InvoiceStatus::PENDING->value);
						})
						->chunkById(200, function($invoices) use ($settings){
							foreach ($invoices as $invoice) {
								SendPaymentReminderJob::dispatch($invoice->id, $settings['content'], $invoice->client_email, $invoice->client_first_name, $invoice->client_last_name, $invoice->currency_code);
							}
						}, 'invoices.id', 'id');

		}


		
    }
}
