<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Client;
use App\Services\InvoiceSettingsService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InvoiceController extends Controller{
    
	/**
	 * searchClients function
	 *
	 * @param Request $request
	 * @return Collection
	 */
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

	/**
	 * fetchInitialData function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchInitialData(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		//try{

			$invoice_settings = new InvoiceSettingsService($company_id);

			return [
				'numbers' 			=> $invoice_settings->getInvoiceNumbers(),
				'product_columns' 	=> $invoice_settings->getProductColumns(),
				'total_fields' 		=> $invoice_settings->getTotalFields(),
			];

		// }catch(Exception $e){
		// 	return General::wentWrong();
		// }

	}

}
