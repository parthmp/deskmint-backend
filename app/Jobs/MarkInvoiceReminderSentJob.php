<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkInvoiceReminderSentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private int $invoice_id)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
		$invoice = Invoice::query()->select(['id', 'reminders_sent'])->find($this->invoice_id);
		
		if($invoice){
			$invoice->reminders_sent = (int) $invoice->reminders_sent + 1;
			$invoice->last_reminder_sent_at = now();
			$invoice->save();
		}

    }
}
