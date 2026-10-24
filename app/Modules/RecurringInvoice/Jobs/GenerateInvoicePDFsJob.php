<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Modules\RecurringInvoice\Actions\ManageActions;
use App\Modules\RecurringInvoice\DB\DB;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB as FacadeDB;
use Illuminate\Support\Str;
use Illuminate\Bus\Batch;

class GenerateInvoicePDFsJob implements ShouldQueue {

	use Queueable, Batchable;

	public $tries = 10;
	public array $backoff = [10, 30, 60, 90, 120, 130, 140, 150, 160, 170];
	
	public function __construct(
		private string $batch_id
	){
		$this->onQueue('pdf-generation');
	}

	/**
	 * filterData function
	 *
	 * @param integer $invoice_id
	 * @param Collection $invoices
	 * @param Collection $snapshots
	 * @return array
	 */
	private function filterData(int $invoice_id, Collection $invoices, Collection $snapshots) : array {

		$invoice = null;
		$snapshot = null;

		
		$invoice = $invoices->filter(
			fn($invoice_row) => (int) $invoice_id === (int) $invoice_row->id
		)->first();

		$snapshot = $snapshots->filter(
			fn($snapshot_row) => (int) $invoice_id === (int) $snapshot_row->invoice_id
		)->first();
		
		return [
			'invoice' 	=> 	$invoice,
			'snapshot'	=>	$snapshot->snapshot
		];

	}

	/**
	 * filterXmlOption function
	 *
	 * @param integer $invoice_id
	 * @param array $xml_options
	 * @return array
	 */
	private function filterXmlOption(int $invoice_id, array $xml_options) : array {

		foreach($xml_options as $option){

			if((int) $option['invoice_id'] === (int) $invoice_id){
				return $option;
			}

		}

		return [];

	}


	/**
	 * handle function
	 *
	 * @return void
	 */
	public function handle() : void {

		if(Redis::exists($this->batch_id)){

			$redis_key = Str::uuid()->toString();
			
			$invoice_ids = Redis::get($this->batch_id);
			$invoice_ids = json_decode($invoice_ids, true);

			$db = app(DB::class);

			$invoices = $db->fetchInvoiceDataForPDFs($invoice_ids, ['id', 'total', 'balance_due', 'status', 'created_at', 'due_date', 'invoice_number']);
			$snapshots = $db->fetchInvoiceSnapshots($invoice_ids);
			$xml_options = $db->xmlInvoiceOptionData($invoice_ids);

			$batched_jobs = [];

			foreach($invoice_ids as $id){
				$filtered = $this->filterData((int) $id, $invoices, $snapshots);
				$invoice_row = $filtered['invoice'];
				$snapshot_data = $filtered['snapshot'];
				$xml_option = $this->filterXmlOption((int) $id, $xml_options);
				$batched_jobs[] = new GenerateSingleInvoicePDFJob($redis_key, $invoice_row, $snapshot_data, $xml_option);
			}

			Bus::batch($batched_jobs)->allowFailures()->finally(static function(Batch $batch) use ($redis_key){

				$db = app(DB::class);

				FacadeDB::transaction(function() use ($redis_key, $db){

					$db->updateInvoiceFilenames($redis_key);

					FacadeDB::afterCommit(function() use ($redis_key){
						Redis::del($redis_key);
					});

				});

			})->dispatch();


			//Redis::del($this->batch_id);
			Redis::del($redis_key);

		}

	}

}