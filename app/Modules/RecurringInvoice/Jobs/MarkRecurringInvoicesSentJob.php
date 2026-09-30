<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Enums\RecurringInvoices\RecurringInvoiceStatus;
use App\Models\RecurringInvoice;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class MarkRecurringInvoicesSentJob implements ShouldQueue {

	use Queueable, Batchable;

	public function __construct(
		private int $company_id,
		private string $redis_key
	){}

	/**
	 * handle function
	 *
	 * @return void
	 */
	public function handle() : void {
		
		$payload = Redis::smembers($this->redis_key);
		
		$data['update_status_ids'] = [];
		$data['do_not_update_status_ids'] = [];

		foreach($payload as $entry){

			$decoded = json_decode($entry, true);

			if((int) $decoded['status'] === (int) RecurringInvoiceStatus::DRAFT->value){
				$data['update_status_ids'][] = (int) $decoded['id'];
			}else{
				$data['do_not_update_status_ids'][] = (int) $decoded['id'];
			}
		}

		$update = [
					'sent_at'			=>	now(),
					'hidden_sent_at'	=>	now(),
					'updated_at'		=>	now()
				];

		foreach($data as $key => $element){

			$update['status'] = RecurringInvoiceStatus::SENT->value;

			if($key === 'do_not_update_status_ids'){
				unset($update['status']);
			}

			$chunks = array_chunk($element, 5000);

			foreach($chunks as $chunk){
				RecurringInvoice::where('company_id', '=', $this->company_id)->whereIn('id', $chunk)->update($update);
			}

		}
		
		Redis::del($this->redis_key);

	}

}