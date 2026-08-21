<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceSnapshot;
use App\Modules\InvoiceGeneration\InvoiceSnapshot as Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateInvoiceSnapshotJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
		private int $company_id,
		private int $invoice_id,
		private bool $generate_invoice = false,
		private bool $send_invoice = false
	){}

    /**
     * Execute the job.
     */
    public function handle(): void
    {

		$invoice = Invoice::select('timezone_offset_minutes')->where([['company_id', '=', $this->company_id], ['id', '=', $this->invoice_id]])->first();

        $snapshot = app(Snapshot::class)
						->setCompanyId($this->company_id)
						->setInvoiceId($this->invoice_id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();

		InvoiceSnapshot::updateOrCreate(
			['invoice_id' 	=> $this->invoice_id],
			['snapshot' 	=> $snapshot]
		);

		if($this->generate_invoice){
			GenerateInvoiceJob::dispatch($this->company_id, $this->invoice_id, $this->send_invoice);
		}
		

    }
}
