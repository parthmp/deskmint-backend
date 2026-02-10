<?php

namespace App\Repositories\Client;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

	/**
	 * deleteRecordsByClientIds function
	 *
	 * @param string $table
	 * @param array $ids
	 * @return void
	 */
	public function deleteRecordsByClientIds(string $table, array $ids) : void {
		if(Schema::hasTable($table)){
			DB::table($table)->whereIn('client_id', $ids)->delete();
		}
		Client::whereIn('id', $ids)->delete();
	}

}