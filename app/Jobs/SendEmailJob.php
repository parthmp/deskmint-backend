<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class SendEmailJob implements ShouldQueue
{
    use Queueable, Batchable;

    /**
     * Create a new job instance.
     */
	public function __construct(
		public string $to,
		public string $to_name,
		public string $mailable_class,
		public array $mailable_data,
		public array $smtp,
		public ?array $cc = null,
		public ?string $redis_key = null,
        public null|array|string $payload = null,
	){}

    /**
     * Execute the job.
     */
    public function handle(): void {

		if($this->batch()?->cancelled()){
            return;
        }

		$mailer = Mail::build([
			'transport' 	=> 'smtp',
			'host' 			=> $this->smtp['host'],
			'port' 			=> $this->smtp['port'],
			'username' 		=> $this->smtp['username'],
			'password' 		=> $this->smtp['password'],
			'encryption' 	=> $this->smtp['encryption'] ?? null,
		]);

		$mailable = (new $this->mailable_class(...$this->mailable_data))->from($this->smtp['from_address'], $this->smtp['from_name'] ?? null);
		$mailer = $mailer->to($this->to, $this->to_name);

		if($this->cc){
			$mailer = $mailer->cc($this->cc);
		}

		$mailer->send($mailable);

		if($this->redis_key && $this->payload){
            Redis::sadd($this->redis_key, $this->payload);
        }

	}
}
