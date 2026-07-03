<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:payment-reminder')]
#[Description('Command description')]
class PaymentReminder extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
		logger("from payment reminder\n");
        echo "works\n";
    }
}
