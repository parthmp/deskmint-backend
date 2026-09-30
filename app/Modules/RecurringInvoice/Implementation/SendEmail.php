<?php

namespace App\Modules\RecurringInvoice\Implementation;

use App\Jobs\SendEmailJob;
use App\Modules\RecurringInvoice\Jobs\MarkRecurringInvoicesSentJob;
use App\Traits\CustomMailSettings;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class SendEmail {

	use CustomMailSettings;

	public function __construct(
		private ParseEmail $parse_email
	){}

	/**
	 * sendEmail function
	 *
	 * @param array $data
	 * @param integer $company_id
	 * @param string $content
	 * @return void
	 */
	public function sendEmail(array $data, int $company_id, string $content) : void {

		$redis_key = Str::uuid()->toString();

		$batch = [];
		
		for($z = 0 ; $z < count($data) ; $z++){
			
			$data[$z]['content'] = $this->parse_email->parse($data[$z], $content);
			$data[$z]['subject'] = 'Recurring invoices scheduled';

			$batch[] = new SendEmailJob(
				to: $data[$z]['email'],
				to_name: $data[$z]['first_name'].' '.$data[$z]['last_name'],
				mailable_class: \App\Mail\SendGenericEmail::class,
				redis_key:$redis_key,
				payload:json_encode(['id' => $data[$z]['id'], 'status' => $data[$z]['status']]),
				mailable_data: [$data[$z]],
				smtp: $this->smtpSettings()
			);
			
		}

		Bus::batch($batch)->allowFailures()->finally(function(Batch $batch) use ($company_id, $redis_key){
			MarkRecurringInvoicesSentJob::dispatch($company_id, $redis_key);
		})->dispatch();
		
	}

}