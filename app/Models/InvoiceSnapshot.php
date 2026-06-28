<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSnapshot extends Model
{
    protected $table = 'invoice_snapshots';

	protected $fillable = [
        'invoice_id',
        'snapshot'
    ];

	protected $casts = [
    	'snapshot' => 'array'
	];
}
