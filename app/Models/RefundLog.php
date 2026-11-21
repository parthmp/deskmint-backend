<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundLog extends Model
{

	use SoftDeletes;

    protected $table = 'refund_logs';
}
