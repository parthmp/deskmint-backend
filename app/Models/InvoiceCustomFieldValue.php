<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceCustomFieldValue extends Model{
    
	use HasFactory, SoftDeletes;

    protected $table = 'invoices_custom_fields_values';

	public function InvoicesCustomField(){
		return $this->belongsTo(InvoicesCustomField::class, 'invoices_custom_field_id');
	}

	public function invoices_custom_field_wt(){
		return $this->belongsTo(InvoicesCustomField::class, 'invoices_custom_field_id')->withTrashed();
	}
}
