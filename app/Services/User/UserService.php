<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\User\UserRepository;

class UserService{

	public function __construct(private UserRepository $user_repository){}

	public function fetchUserByEmail(string $email) : ?User {
		return $this->user_repository->fetchByEmail($email);
	}

}