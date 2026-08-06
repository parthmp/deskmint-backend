<?php

namespace App\Jobs;

use App\Traits\CustomMailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendGenericEmailJob implements ShouldQueue
{
    use Queueable, CustomMailSettings;

    /**
     * Create a new job instance.
     */
    public function __construct(private array $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
		
        SendEmailJob::dispatch(
			to: $this->data['email'],
			to_name: $this->data['first_name'].' '.$this->data['last_name'],
			mailable_class: \App\Mail\SendGenericEmail::class,
			mailable_data: [$this->data],
			smtp: $this->smtpSettings()
		);
    }
}
