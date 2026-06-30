<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionGatewayDetail extends Model
{
	use SoftDeletes;
	
    protected $table = 'transaction_gateway_details';

	protected $fillable = [
		'transaction_id',
		'payment_approved_details',
		'payment_captured_details',
		'echeck_pending_details'
	];
}
