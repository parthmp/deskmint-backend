<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Jobs\SendEmailJob;
use App\Modules\InvoiceGeneration\InvoiceEmailContent;
use App\Modules\RecurringInvoice\DB\DB;
use App\Traits\CustomMailSettings;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB as FacadeDB;
use Illuminate\Support\Str;

class SendInvoiceGeneratedEmailsJob implements ShouldQueue {

	use Queueable, Batchable, CustomMailSettings;

	public $tries = 10;
	public array $backoff = [10, 30, 60, 90, 120, 130, 140, 150, 160, 170];

	public function __construct(
		private int $company_id,
		private string $batch_id
	){}

	/**
	 * handle function
	 *
	 * @return void
	 */
	public function handle() : void {
		
		if(Redis::exists($this->batch_id)){

			$invoice_ids = Redis::get($this->batch_id);
			$invoice_ids = json_decode($invoice_ids, true);
			
			$db = app(DB::class);

			$invoices = $db->fetchDataForSendingInvoices($invoice_ids);

			$redis_key = Str::uuid()->toString();

			$smtp = $this->smtpSettings();

			$all_settings = $db->fetchSettings((int) $this->company_id);
			$email_template = $db->fetchRecurringInvoiceEmailContentSettings($all_settings);

			$content_class = new InvoiceEmailContent();
			$content_class = $content_class->setDisk(INVOICES_DISK)->setSubscription(true)->setInvoiceContent((string) $email_template['recurring_invoice_email_content_invoice']);

			$batched_jobs = [];

			foreach($invoices as $invoice){
				
				$content = $content_class->setInvoice($invoice)->getContent();
				
				$batched_jobs[] = new SendEmailJob(
					to: $invoice->client_wt->email,
					to_name: $invoice->client_wt->first_name.' '.$invoice->client_wt->last_name,
					mailable_class: \App\Mail\SendInvoice::class,
					mailable_data: [$content],
					redis_key:$redis_key,
					payload:$invoice->id,
					smtp: $smtp
				);

			}

			if(!empty($batched_jobs)){

				Bus::batch($batched_jobs)->allowFailures()->finally(static function(Batch $batch) use ($redis_key){

					$db = app(DB::class);

					FacadeDB::transaction(function() use ($redis_key, $db){

						$db->updateSentAtForInvoices($redis_key);

						FacadeDB::afterCommit(function() use ($redis_key){
							Redis::del($redis_key);
						});

					});

					

				})->dispatch();

			}

			Redis::del($this->batch_id);

		}

	}


}