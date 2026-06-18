<?php

use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\InvoiceGeneration\InvoiceGenerator;
use App\Modules\InvoiceGeneration\InvoiceSettingsResolver;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
	//return (new InvoiceGenerator(1, 4))->modifyInvoiceTemplate()->getInvoiceHTML();
	//return (new InvoiceGenerator(1, 52, 330))->generatePDF(save:true, add_random:false)->stream();
	//return (new InvoiceGenerator(1, 4, 330))->generatePDF(save:true, add_random:true)->generateEInvoice()->sendEmail();
	return app(InvoiceGenerator::class)->setCompanyId(1)->setInvoiceId(70)->setTimeOffsetMinutes(330)->exec()->generatePDF(save: true, add_random: false)->stream();
    return 'welcome';
});
