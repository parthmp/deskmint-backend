<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientCustomFieldValue extends Model{

	use HasFactory;

    protected $table = 'clients_custom_fields_values';
}
