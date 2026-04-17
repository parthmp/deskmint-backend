<?php

use App\Modules\InvoiceGeneration\InvoiceGenerator;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
	//return (new InvoiceGenerator(1, 4))->modifyInvoiceTemplate()->getInvoiceHTML();
	return (new InvoiceGenerator(1, 4))->modifyInvoiceTemplate()->generatePDF();
    //return 'welcome';
});
