<?php

namespace App\Jobs;

use App\Models\PaymentRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkPaymentRequestReminderSentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
		private int $pr_id
	){}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $pr = PaymentRequest::where('id', '=', $this->pr_id)->first();
		if($pr){
			$pr->reminders_sent = ((int) $pr->reminders_sent + 1);
			$pr->last_reminder_sent_at = now();
			$pr->hidden_sent_at = now();
			$pr->save();
		}
    }
}
