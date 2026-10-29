<?php

namespace App\Modules\RecurringInvoice\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class ClearChainFlagJob implements ShouldQueue {
	
	use Queueable, Batchable;

	public function __construct(){}

	public function handle(): void {
		Redis::del(INVOICE_CHAIN_RUNNING);
	}
}