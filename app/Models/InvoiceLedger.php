<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceLedger extends Model
{
	use SoftDeletes;
	
    protected $table = 'invoice_ledger';

}
