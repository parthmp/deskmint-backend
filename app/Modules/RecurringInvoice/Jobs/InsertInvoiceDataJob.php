<?php

namespace App\Modules\RecurringInvoice\Jobs;

use App\Modules\RecurringInvoice\Actions\ManageActions;
use App\Modules\RecurringInvoice\DB\DB;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB as FacadeDB;
use Illuminate\Support\Str;

class InsertInvoiceDataJob implements ShouldQueue {

	use Queueable, Batchable;

	public $tries = 10;
	public array $backoff = [10, 30, 60, 90, 120, 130, 140, 150, 160, 170];

	public function __construct(
		private int $company_id,
		private array $recurring_invoice_ids,
		private string $batch_id,
		private ?array $online_transaction_data = null
	){}


	/**
	 * fetchData function
	 *
	 * @return array
	 */
	private function fetchData() : array {

		//fetch
		$db = app(DB::class);
		$recurring_invoice_data = $db->fetchRecurringInvoiceData($this->company_id, $this->recurring_invoice_ids, ['recurring_invoices.*', 'clients.first_name', 'clients.last_name', 'clients.client_company_name']);
		
		$recurring_invoice_items = $db->fetchRecurringInvoiceItems($this->recurring_invoice_ids);
		$recurring_invoice_custom_product_columns = $db->fetchCustomProductColumns($this->recurring_invoice_ids);
		$recurring_invoice_custom_field_values = $db->fetchCustomFieldValues($this->recurring_invoice_ids);
		$recurring_invoice_flat_table_rows = $db->fetchFlatTableRows($this->recurring_invoice_ids);
		
		return [
			'data'												=>	$recurring_invoice_data,
			'recurring_invoice_items'							=>	$recurring_invoice_items,
			'recurring_invoice_custom_product_columns'			=>	$recurring_invoice_custom_product_columns,
			'recurring_invoice_custom_field_values'				=>	$recurring_invoice_custom_field_values,
			'recurring_invoice_flat_table_rows'					=>	$recurring_invoice_flat_table_rows
		];

	}


	/**
	 * handle function
	 *
	 * @return void
	 */
	public function handle() : void {
		
		if(Redis::exists(config('global.invoice_lock_key'))){
			throw new Exception("invoice insertion locked, retry might happen.");
		}

		$actions = app(ManageActions::class);

		try{

			//lock
			Redis::setex(config('global.invoice_lock_key'), (int) config('global.invoice_lock_seconds'), true);

			FacadeDB::transaction(function () use ($actions) {
				
				
				$data = $this->fetchData();
				$inserted = $actions->setCompany($this->company_id)
						->setInvoiceData($data)
						->insertInvoiceWithData($this->online_transaction_data)
						->insertInvoiceItems()
						->insertCustomProductColumns()
						->insertCustomFieldValues()
						->insertPayments($this->online_transaction_data)
						->insertSnapshots()
						->updateLastInvoiceCreatedAt()
						->updateNextInvoiceOn()
						->getInserted();

				FacadeDB::afterCommit(function() use ($inserted){
					$invoice_ids = array_column($inserted, 'id');
					Redis::setex($this->batch_id, 10800, json_encode($invoice_ids));
				});

			});

		}finally{
			Redis::del(config('global.invoice_lock_key'));
		}

		
		

	}

}