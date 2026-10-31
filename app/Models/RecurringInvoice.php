<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class RecurringInvoice extends Model
{
	use SoftDeletes;

    protected $table = 'recurring_invoices';

	public function currency(){
		return $this->belongsTo(Currency::class, 'currency_id');
	}
	
}
