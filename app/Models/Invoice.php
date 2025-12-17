<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model{
    
	use SoftDeletes;

	protected $table = 'invoices';

	public function client(){
		return $this->belongsTo(Client::class, 'id');
	}

	public function client_wt(){
		return $this->belongsTo(Client::class, 'id')->withTrashed();
	}

	public function company(){
		return $this->belongsTo(Company::class, 'id');
	}

	public function company_wt(){
		return $this->belongsTo(Company::class, 'id')->withTrashed();
	}

}
