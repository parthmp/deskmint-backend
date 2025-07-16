<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TwoFactorAuthToken extends Model
{	
	use SoftDeletes;

    protected $table = '2fa_tokens';

	public function user(){
		return $this->belongsTo(User::class, 'user_id');
	}

}
