<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomFieldType extends Model{

	use HasFactory, SoftDeletes;

	protected $table = 'custom_field_types';
    
}
