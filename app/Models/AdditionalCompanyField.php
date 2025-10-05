<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdditionalCompanyField extends Model{

	use SoftDeletes, HasFactory;

    protected $table = 'additional_company_fields';

}
