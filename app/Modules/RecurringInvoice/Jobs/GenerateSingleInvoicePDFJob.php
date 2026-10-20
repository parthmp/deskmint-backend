<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Models\Invoice;
use App\Modules\InvoiceGeneration\InvoiceGenerator;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
class GenerateSingleInvoicePDFJob implements ShouldQueue {

	use Queueable, Batchable;

	public $tries = 5;
	public array $backoff = [10, 20, 30, 40, 60];

	public function __construct(
		private string $redis_key,
		private Invoice $invoice,
		private array $snapshot,
		private array $xml_option
	){
		$this->onQueue('pdf-generation');
	}

	public function handle() : void {

		$key_existed = Redis::exists($this->redis_key);

		$invoice_generator = app(InvoiceGenerator::class)->setCompanyId((int) $this->invoice->company_id)->setInvoiceId((int) $this->invoice->id)->exec($this->invoice, $this->snapshot);
		$invoice_generator = $invoice_generator->generatePDF(save:true, add_random:false);

		$xml_on = (int) $this->xml_option['e_invoice_enabled'] === 1;
		
		if($xml_on){
			$invoice_generator = $invoice_generator->generateEInvoice();
		}
		
		$filename = $invoice_generator->getFilename();
		$pdf = $filename.'.pdf';
		$xml = '';
		if($xml_on){
			$xml = $filename.'.xml';
		}
		
		$data = [
			'pdf'				=>	$pdf,
			'xml'				=>	$xml,
			'invoice_id'		=>	$this->invoice->id
		];

		Redis::rPush($this->redis_key, json_encode($data));

		if(!$key_existed){
			Redis::expire($this->redis_key, 86400);
		}

	}

}