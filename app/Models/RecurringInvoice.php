<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class RecurringInvoice extends Model
{
	use SoftDeletes;

    protected $table = 'recurring_invoices';

	public function currency(){
		return $this->belongsTo(Currency::class, 'currency_id');
	}

	public function client(){
		return $this->belongsTo(Client::class, 'client_id');
	}

	public function client_wt(){
		return $this->belongsTo(Client::class, 'client_id')->withTrashed();
	}

	public function company(){
		return $this->belongsTo(Company::class, 'company_id');
	}

	public function company_wt(){
		return $this->belongsTo(Company::class, 'company_id')->withTrashed();
	}

	public function payment_type(){
		return $this->belongsTo(PaymentType::class, 'payment_type_id')->withTrashed();
	}
	
}
