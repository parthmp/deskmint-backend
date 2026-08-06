<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRequest extends Model
{
	use SoftDeletes;
	
    protected $table = 'payment_requests';

	public function currency(){
		return $this->belongsTo(Currency::class, 'currency_id');
	}

}
