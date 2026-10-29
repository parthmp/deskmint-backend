<?php

namespace App\Console\Commands;

use App\Models\RecurringInvoice as ModelsRecurringInvoice;
use App\Modules\Payment\Enums\PaymentGateway;
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

		if(Redis::exists(INVOICE_CHAIN_RUNNING)){
			logger('another invoice chain is already running');
			return ;
		}

		if(Redis::exists(config('global.invoice_lock_key'))){
			logger('invoices are locked');
			return ;
		}
		
		$gen_number = (int) config('global.recurring_invoice_batch_number');

		$ids = ModelsRecurringInvoice::where([['payment_gateway', '=', PaymentGateway::NONE->value], ['next_invoice_on', '<', now()]])->orderBy('id', 'desc')->limit($gen_number)->get()->pluck('id')->toArray();
		
		if(!empty($ids)){
			$recurring_invoice = app(RecurringInvoice::class);
			$recurring_invoice->setCompanyId(1)->setRecurringInvoiceIds($ids)->executeActions(false, true);
		}

    }
}
