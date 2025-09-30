<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceCustomFieldValue extends Model{
    
	use HasFactory;

    protected $table = 'invoices_custom_fields_values';

	public function InvoicesCustomField(){
		return $this->belongsTo(InvoicesCustomField::class, 'invoices_custom_field_id');
	}
}
