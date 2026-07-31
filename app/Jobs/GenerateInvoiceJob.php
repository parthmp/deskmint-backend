<?php

namespace App\Jobs;

use App\Services\Invoice\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateInvoiceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
		private int $company_id,
		private int $invoice_id,
		private bool $do_send = false
	){}

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoice_service): void
    {
        $invoice_service->generateInvoice($this->company_id, $this->invoice_id);
		
		if($this->do_send){
			$data = $invoice_service->prepareEmailData($this->invoice_id);
			SendInvoiceEmailJob::dispatch($data['invoice'], $data);
		}
    }
}
