<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringInvoiceUrl extends Model
{
	use SoftDeletes;
	
    protected $table = 'recurring_invoice_urls';
}
