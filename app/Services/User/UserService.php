<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\User\UserRepository;

/**
 * UserService class
 */
class UserService{
	
	/**
	 * __construct function
	 *
	 * @param UserRepository $user_repository
	 */
	public function __construct(private UserRepository $user_repository){}

	/**
	 * fetchUserByEmail function
	 *
	 * @param string $email
	 * @return User|null
	 */
	public function fetchUserByEmail(string $email) : ?User {
		return $this->user_repository->fetchByEmail($email);
	}

}