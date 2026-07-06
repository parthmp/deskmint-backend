<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Traits\CustomMailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Queueable, CustomMailSettings;

    /**
     * Create a new job instance.
     */
    public function __construct(private Invoice $invoice, private array $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
		
        SendEmailJob::dispatch(
			to: $this->invoice->client_wt->email,
			to_name: $this->invoice->client_wt->first_name.' '.$this->invoice->client_wt->last_name,
			mailable_class: \App\Mail\SendInvoice::class,
			mailable_data: [$this->data],
			smtp: $this->smtpSettings()
		);
    }
}
