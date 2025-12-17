<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientCustomFieldValue extends Model{

	use HasFactory, SoftDeletes;

    protected $table = 'clients_custom_fields_values';

	public function ClientsCustomField(){
		return $this->belongsTo(ClientsCustomField::class, 'clients_custom_field_id');
	}

	public function clients_custom_field_wt(){
		return $this->belongsTo(ClientsCustomField::class, 'clients_custom_field_id')->withTrashed();
	}

}
