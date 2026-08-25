<?php

namespace App\Http\Controllers;

use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

class PaymentsController extends Controller {
    
	// private array $additional_fields = [
	// 	[
	// 		'label'			=>	'c_code',
	// 		'text'			=>	'Currency'
	// 	],
	// 	[
	// 		'label'			=>	'full_name',
	// 		'text'			=>	'Name'
	// 	],
	// 	[
	// 		'label'			=>	'applied_amount',
	// 		'text'			=>	'Applied'
	// 	],
	// 	[
	// 		'label'			=>	'amount_left_to_be_applied',
	// 		'text'			=>	'Amount left'
	// 	]
		
	// ];

	// private array $date_fields = [
	// 	'created_at'
	// ];

	public function __construct(
		private PaymentService $payment_service,
		private ArrangedDataTableColumns $arranged_data_table_columns
	){}

	

}
