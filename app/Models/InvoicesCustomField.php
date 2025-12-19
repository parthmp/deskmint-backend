<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicesCustomField extends Model{
	
    use SoftDeletes, HasFactory;

	protected $table = 'invoices_custom_fields';

	public function customFieldType(){
		return $this->belongsTo(CustomFieldType::class, 'custom_field_type_id');
	}

	public function customFieldValue(){
		return $this->hasOne(InvoiceCustomFieldValue::class);
	}

	public function custom_field_type_wt(){
		return $this->belongsTo(CustomFieldType::class, 'custom_field_type_id')->withTrashed();
	}

}
