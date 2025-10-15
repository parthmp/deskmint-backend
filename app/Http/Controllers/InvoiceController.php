<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\Client;
use Illuminate\Http\Request;

class InvoiceController extends Controller{
    
	public function searchClients(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));
		$searched = Sanitize::input($request->input('searched'));

		$clients = Client::select('id', 'first_name', 'last_name')->where('company_id', '=', $company_id)->where(function($query) use($searched){
			$query->where('first_name', 'LIKE', '%'.$searched.'%');
			$query->orwhere('last_name', 'LIKE', '%'.$searched.'%');
		})->orderBy('first_name', 'ASC')->limit(50)->get()->map(function($client){
			return [
				'text'		=>	$client->first_name.' '.$client->last_name,
				'value'		=>	$client->id
			];
		})->toArray();

		return $clients;

	}

}
