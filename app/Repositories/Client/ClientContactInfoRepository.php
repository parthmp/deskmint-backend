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

}