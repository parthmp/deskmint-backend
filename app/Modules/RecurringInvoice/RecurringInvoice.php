<?php

namespace App\Modules\RecurringInvoice;

use App\Modules\RecurringInvoice\DB\DB;
use App\Modules\RecurringInvoice\Exceptions\RecurringInvoiceModuleException;
use App\Modules\RecurringInvoice\Jobs\InsertInvoiceDataJob;
use App\Modules\RecurringInvoice\Jobs\SendRecurringInvoiceJob;
use Illuminate\Support\Facades\Bus;

class RecurringInvoice {

	private int $company_id;
	private array $recurring_invoice_ids = [];

	public function __construct(
		private DB $db
	){}

	/**
	 * setRecurringInvoiceIds function
	 *
	 * @param array $recurring_invoice_ids
	 * @return self
	 */
	public function setRecurringInvoiceIds(array $recurring_invoice_ids) : self {
		$this->recurring_invoice_ids = $recurring_invoice_ids;
		return $this;
	}

	/**
	 * setCompanyId function
	 *
	 * @param integer $company_id
	 * @return self
	 */
	public function setCompanyId(int $company_id) : self {
		$this->company_id = $company_id;
		return $this;
	}

	/**
	 * executeActions function
	 *
	 * @param boolean $send_email
	 * @param boolean $generate_invoice
	 * @param boolean $mark_paid
	 * @return void
	 */
	public function executeActions(bool $send_email, bool $generate_invoice, bool $mark_paid) : void {

		//fetch everything at once and chain jobs here.
		if(empty($this->recurring_invoice_ids) || !isset($this->company_id)){
			throw new RecurringInvoiceModuleException('Invalid data provided', 'invalid_data_recurring_invoice_module', (int) config('global.error_code'));
		}

		$batch = [];

		if($send_email){
			$batch[] = new SendRecurringInvoiceJob($this->recurring_invoice_ids, $this->company_id);
		}

		if($generate_invoice){
			$batch[] = new InsertInvoiceDataJob($this->company_id, $this->recurring_invoice_ids);
		}

		if(!empty($batch)){
			Bus::chain($batch)->dispatch();
		}

	}

}