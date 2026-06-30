<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
	use SoftDeletes;
    protected $table = 'transactions';

	protected $casts = [
		'paid_at'	=>	'datetime'
	];

	public function invoice_wt(){
		return $this->belongsTo(Invoice::class, 'invoice_id')->withTrashed();
	}

}
