<?php

use App\Modules\InvoiceGeneration\InvoiceGenerator;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
	return (new InvoiceGenerator(1, 3))->generateInvoice();
    //return 'welcome';
});
