<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceTotal extends Model
{
	use SoftDeletes;
	
    protected $table = 'invoice_totals';

}
