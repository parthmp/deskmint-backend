<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model{
    
	use SoftDeletes, HasFactory;

	protected $table = 'clients';

	public function billing_country(){
		return $this->belongsTo(Country::class, 'billing_country_id', 'id');
	}

	public function shipping_country(){
		return $this->belongsTo(Country::class, 'shipping_country_id', 'id');
	}

	public function currency(){
		return $this->belongsTo(Currency::class, 'currency_id', 'id');
	}

	public function industry(){
		return $this->belongsTo(Industry::class, 'industry_id', 'id');
	}

}
