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
		private InvoiceService $invoice_service
	){}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->invoice_service->generateInvoice($this->company_id, $this->invoice_id);
    }
}
