<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $table = 'system_notifications';

	protected $fillable = [
		'company_id', 'type', 'title', 'message', 'data'
	];
}
