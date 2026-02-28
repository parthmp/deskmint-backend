<?php

namespace App\Repositories\Client;

use App\Models\ClientContactInfo;
use Illuminate\Database\Eloquent\Collection;

class ClientContactInfoRepository{

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return Collection|null
	 */
	public function fetchById(int $id) : ?Collection{
		return ClientContactInfo::where('client_id', '=', $id)->get();
	}

	/**
	 * upsertInfo function
	 *
	 * @param array $upsert_array
	 * @param array $identifiers
	 * @param array $columns
	 * @return void
	 */
	public function upsertInfo(array $upsert_array, array $identifiers, array $columns) : void {
		ClientContactInfo::upsert($upsert_array, $identifiers, $columns);
	}

}