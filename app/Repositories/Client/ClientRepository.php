<?php

namespace App\Repositories\Client;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientRepository{

	public function __construct(private ClientContactInfoRepository $client_contact_info_repository){}

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

	/**
	 * fetchAllDataById function
	 *
	 * @param integer $id
	 * @return Client|null
	 */
	public function fetchAllDataById(int $id) : ?Client {
		return Client::where('id', '=', $id)->with('billing_country')->with('shipping_country')->with('currency')->with('industry')->first();
	}

	/**
	 * fetchClientContactInfoById function
	 *
	 * @param integer $id
	 * @return Collection|null
	 */
	public function fetchClientContactInfoById(int $id) : ?Collection {
		return $this->client_contact_info_repository->fetchById($id);
	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return Client|null
	 */
	public function fetchById(int $id) : ?Client {
		return Client::where('id', '=', $id)->first();
	}

	/**
	 * createEmpty function
	 *
	 * @return Client
	 */
	public function createEmpty() : Client {
		return new Client();
	}

	/**
	 * createOrUpdate function
	 *
	 * @param Client $client
	 * @return array
	 */
	public function createOrUpdate(Client $client, array $data) : array {

		$client->company_id = $data['company_id'];
		$client->first_name = $data['personal_info_first_name'];
		$client->last_name = $data['personal_info_last_name'];
		$client->tax_number = $data['personal_info_tax_id'];
		$client->website = $data['website'];
		$client->email = $data['email'];
		$client->phone = $data['phone'];
		
		$client->billing_street = $data['billing_street'];
		$client->billing_apt = $data['billing_apt'];
		$client->billing_city = $data['billing_city'];
		$client->billing_state = $data['billing_state'];
		$client->billing_postal_code = $data['billing_postal_code'];
		$client->billing_country_id = $data['billing_country_id'];

		$client->shipping_street = $data['shipping_street'];
		$client->shipping_apt = $data['shipping_apt'];
		$client->shipping_city = $data['shipping_city'];
		$client->shipping_state = $data['shipping_state'];
		$client->shipping_postal_code = $data['shipping_postal_code'];
		$client->shipping_country_id = $data['shipping_country_id'];

		$client->currency_id = $data['currency_id'];
		$client->payment_terms = $data['payment_terms'];
		$client->quote_valid_days = $data['quote_valid_days'];
		$client->send_reminders = $data['send_reminders'];
		$client->size = $data['size'];
		$client->industry_id = $data['industry_id'];
		$saved = $client->save();

		return [$saved, $client->id];

	}

}