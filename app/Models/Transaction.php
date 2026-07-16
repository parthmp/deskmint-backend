<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
	use SoftDeletes, HasFactory;
    protected $table = 'transactions';

	protected $casts = [
		'paid_at'	=>	'datetime'
	];

	public function invoice_wt(){
		return $this->belongsTo(Invoice::class, 'invoice_id')->withTrashed();
	}

	public function payment_url(){
		return $this->hasOne(PaymentUrl::class, 'transaction_id');
	}

}
