<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model{
    
	use SoftDeletes;

	protected $table = 'invoices';

	protected $casts = [
		'invoice_date' => 'datetime',
		'due_date'     => 'datetime',
	];

	public function client(){
		return $this->belongsTo(Client::class, 'client_id');
	}

	public function client_wt(){
		return $this->belongsTo(Client::class, 'client_id')->withTrashed();
	}

	public function company(){
		return $this->belongsTo(Company::class, 'company_id');
	}

	public function company_wt(){
		return $this->belongsTo(Company::class, 'company_id')->withTrashed();
	}

}
