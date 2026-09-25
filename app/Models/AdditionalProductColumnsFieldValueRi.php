<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdditionalProductColumnsFieldValueRi extends Model
{
    use SoftDeletes, HasFactory;
    
	protected $table = 'additional_product_columns_field_values_ri';

	public function custom_product_field(){
		return $this->belongsTo(AdditionalProductColumnsField::class, 'apc_field_id', 'id');
	}

	public function invoice_item(){
    	return $this->belongsTo(RecurringInvoiceItem::class, 'row_uuid', 'row_uuid');
	}
}
