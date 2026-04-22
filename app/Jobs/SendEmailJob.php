<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
	public function __construct(
		public string $to,
		public string $to_name,
		public string $mailable_class,
		public array $mailable_data,
		public array $smtp
	){}

    /**
     * Execute the job.
     */
    public function handle(): void {

		$mailer = Mail::build([
			'transport' 	=> 'smtp',
			'host' 			=> $this->smtp['host'],
			'port' 			=> $this->smtp['port'],
			'username' 		=> $this->smtp['username'],
			'password' 		=> $this->smtp['password'],
			'encryption' 	=> $this->smtp['encryption'] ?? null,
		]);

		$mailable = (new $this->mailable_class(...$this->mailable_data))->from($this->smtp['from_address'], $this->smtp['from_name'] ?? null);
		$mailer->to($this->to, $this->to_name)->send($mailable);

	}
}
