<?php

namespace App\Jobs;

use App\Services\PaymentRequest\PaymentRequestService;
use App\Traits\CustomMailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

class SendPaymentReminderForPaymentRequestJob implements ShouldQueue
{
    use Queueable, CustomMailSettings;

    /**
     * Create a new job instance.
     */
   	public function __construct(
		private string $pr_id,
		private string $uuid,
		private string $email_content,
		private string $client_email,
		private string $client_first_name,
		private string $client_last_name,
		private string $amount,
		private string $payment_gateway,
		private string $currency_code
	){}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $parse_data = [
			'currency'			=>	$this->currency_code,
			'first_name'		=>	$this->client_first_name,
			'last_name'			=>	$this->client_last_name,
			'payment_gateway'	=>	$this->payment_gateway,
			'uuid'				=>	$this->uuid,
			'amount'			=>	$this->amount
		];
		$content = app(PaymentRequestService::class)->parseEmailContent($this->email_content, $parse_data, 'payment_request.pay');

		$data = [
			'subject'		=>	'Reminder for the payment request for '.$this->amount,
			'first_name'	=>	$this->client_first_name,
			'last_name'		=>	$this->client_last_name,
			'content'		=>	$content
		];

		Bus::chain([
			new SendEmailJob(
				to: $this->client_email,
				to_name: $this->client_first_name.' '.$this->client_last_name,
				mailable_class: \App\Mail\SendGenericEmail::class,
				mailable_data: [$data],
				smtp: $this->smtpSettings()
			),
			new MarkPaymentRequestReminderSentJob((int) $this->pr_id)
		])->dispatch();
    }
}
