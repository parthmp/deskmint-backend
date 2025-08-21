<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContactInfo extends Model{
    
	use SoftDeletes;

	protected $table = 'clients_contact_info';

}
