<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
	use SoftDeletes, HasFactory;
    protected $table = 'companies';

	public function additional_fields(){
		return $this->hasMany(AdditionalCompanyField::class, 'company_id', 'id');
	}

}
