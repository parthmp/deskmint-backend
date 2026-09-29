<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Modules\RecurringInvoice\Implementation\SendEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReccuringInvoiceJob implements ShouldQueue
{
    use Queueable;
	
	public function __construct(
		private array $data,
		private string $content
	){}


	/**
	 * handle function
	 *
	 * @return void
	 */
	public function handle() : void {

		$send_email = app(SendEmail::class);
		$send_email->sendEmail($this->data, $this->content);

	}

}