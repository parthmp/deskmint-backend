<?php

namespace App\Repositories\Client;

use App\Models\Client;

class ClientRepository{

	/**
	 * searchByName function
	 *
	 * @param integer $company_id
	 * @param string $searched
	 * @return array
	 */
	public function searchByName(int $company_id, string $searched) : array {

		$clients = Client::select('id', 'first_name', 'last_name', 'currency_id')->where('company_id', '=', $company_id)->where(function($query) use($searched){
			$query->where('first_name', 'LIKE', '%'.$searched.'%');
			$query->orwhere('last_name', 'LIKE', '%'.$searched.'%');
		})->with('currency')->orderBy('first_name', 'ASC')->limit(50)->get()->map(function($client){
			return [
				'text'		=>	$client->first_name.' '.$client->last_name,
				'value'		=>	$client->id,
				'data'		=>	[
					'currency'	=>	$client->currency
				]
			];
		})->toArray();

		return $clients;
		
	}

}