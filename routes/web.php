<?php

use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\InvoiceGeneration\InvoiceGenerator;
use App\Modules\InvoiceGeneration\InvoiceSettingsResolver;
use App\Modules\InvoiceGeneration\InvoiceSnapshot;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
	//return (new InvoiceGenerator(1, 4))->modifyInvoiceTemplate()->getInvoiceHTML();
	//return (new InvoiceGenerator(1, 52, 330))->generatePDF(save:true, add_random:false)->stream();
	//return (new InvoiceGenerator(1, 4, 330))->generatePDF(save:true, add_random:true)->generateEInvoice()->sendEmail();
	//return app(InvoiceGenerator::class)->setCompanyId(1)->setInvoiceId(70)->setTimeOffsetMinutes(330)->exec()->generatePDF(save: true, add_random: false)->stream();
	$snapshot = app(InvoiceSnapshot::class)->setCompanyId(1)->setInvoiceId(88)->setTimezoneOffset(-330)->setLogoSnapsot()->setGeneralSettings()->setClientSnapshot()->setCompanySnapshot()->setInvoiceSnapshot()->setInvoiceRowsSnapshot()->setTotalsSnapshot()->setTermsSnapshot();
	return $snapshot->output();
    return 'welcome';
});
