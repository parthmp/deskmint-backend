<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientsCustomField extends Model{
	
	use SoftDeletes, HasFactory;

	protected $table = 'clients_custom_fields';

	public function customFieldType(){
		return $this->belongsTo(CustomFieldType::class, 'custom_field_type_id');
	}

}
