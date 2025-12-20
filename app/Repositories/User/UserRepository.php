<?php

namespace App\Repositories\User;

use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;

class UserRepository{

	/**
	 * fetchByEmail function
	 *
	 * @return User|null
	 */
	public function fetchByEmail(string $email) : User|null {
		return User::where('email', '=', $email)->first();
	}

	public function fetchByEmailExceptId(string $email, int $id) : User|null {
		return User::where([['email', '=', $email], ['id', '<>', $id]])->first();
	}

	/**
	 * create function
	 *
	 * @param array $data
	 * @return User
	 */
	public function create(array $data) : User {

		$user = new User();
		$user->name = $data['name'];
		$user->email = $data['email'];
		$user->password = Hash::make($data['password']);
		$user->user_type = $data['user_type'];
		$user->save();
		return $user;
	}

	public function update(array $data, int $id): User {

		$user = User::findOrFail($id);

		if(!$user){
			throw new ModelNotFoundException('invalid_user');
		}

		$update = [
			'name'	=>	$data['name'],
			'email'	=>	$data['email']
		];

		if(isset($data['password']) && trim($data['password']) !== ''){
			$update['password'] = $data['password'];
		}

		$user->update($update);
		return $user->fresh();

	}

}