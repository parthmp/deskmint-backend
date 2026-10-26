<?php

namespace App\Console\Commands;

use App\Models\RecurringInvoice as ModelsRecurringInvoice;
use App\Modules\RecurringInvoice\RecurringInvoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

#[Signature('deskmint:generate-recurring-invoices')]
#[Description('Creates invoices from recurring invoices')]
class CreateInvoicesFromRecurringInvoices extends Command
{


    /**
     * Execute the console command.
     */
    public function handle()
    {
		//Redis::del(config('global.invoice_lock_key'));
		// Redis::setex(config('global.invoice_lock_key'), 10, true);
		// echo "c";
		// die();
		$ids = ModelsRecurringInvoice::orderBy('id', 'desc')->limit(20)->get()->pluck('id')->toArray();
		
		$recurring_invoice = app(RecurringInvoice::class);
        $recurring_invoice->setCompanyId(1)->setRecurringInvoiceIds($ids)->executeActions(false, true);
        // $recurring_invoice->setCompanyId(1)->setRecurringInvoiceIds([109])->setOnlineTransactionData([
		// 	[
		// 		'recurring_invoice_id'  => 109,
		//  		'transaction_id'        => 101
		// 	]
		// ])->executeActions(false, true);
    }
}
