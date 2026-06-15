<?php

use App\Modules\InvoiceGeneration\InvoiceGenerator;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
	//return (new InvoiceGenerator(1, 4))->modifyInvoiceTemplate()->getInvoiceHTML();
	return (new InvoiceGenerator(1, 52, 330))->generatePDF(save:true, add_random:false)->stream();
	//return (new InvoiceGenerator(1, 4, 330))->generatePDF(save:true, add_random:true)->generateEInvoice()->sendEmail();
    return 'welcome';
});
