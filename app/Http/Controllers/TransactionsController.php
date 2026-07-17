<?php

namespace App\Http\Controllers;

use App\Modules\DataTable\Requests\DataTableRequest;
use App\Services\Transaction\TransactionService;
use Illuminate\Http\Request;

class TransactionsController extends Controller {

	public function __construct(
		private TransactionService $transaction_service
	){}

	public function index(DataTableRequest $request){

		$data = $request->validated();
		
		return $this->transaction_service->fetch($request);

	}

}
