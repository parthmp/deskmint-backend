<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model {

	use SoftDeletes, HasFactory;

    protected $table = 'invoice_items';

	public function product(){
		return $this->belongsTo(Product::class, 'product_id');
	}

	public function custom_field_values(){
    	return $this->hasMany(AdditionalProductColumnsFieldValue::class, 'row_uuid', 'row_uuid');
	}
}
