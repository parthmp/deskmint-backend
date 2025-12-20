<?php

namespace App\Repositories\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AdminRepository{

	/**
	 * fetchAll function
	 *
	 * @return Collection|null
	 */
	public function fetchAll() : Collection|null {
		return User::where('user_type', '=', config('global.user_types.admin'))->orderBy('name', 'asc')->get();
	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return User|null
	 */
	public function fetchById(int $id) : User|null {
		return User::where([['id', '=', $id], ['user_type', '=', config('global.user_types.admin')]])->first();
	}

}