<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomPasswordReset extends Model
{
	use SoftDeletes, HasFactory;

    protected $table = 'custom_password_resets';

	public function user(){
		return $this->belongsTo(User::class, 'user_id');
	}
	
}
