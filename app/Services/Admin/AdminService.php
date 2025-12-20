<?php

namespace App\Services\Admin;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\User;
use App\Repositories\Admin\AdminRepository;
use App\Repositories\User\UserRepository;
use Exception;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminService{

	/**
	 * __construct function
	 *
	 * @param AdminRepository $admin_repository
	 */
	public function __construct(private AdminRepository $admin_repository, private UserRepository $user_repository){
		
	}

	/**
	 * fetchIndex function
	 *
	 * @return array
	 */
	public function fetchIndex() : array {

		return [
			'columns' => [
				[
					'label' => 	'name',
					'text'	=>	'Name'
				],
				[
					'label'	=>	'email',
					'text'	=>	'Email'
				],
				[
					'label'	=>	'created_at',
					'text'	=>	'Added on'
				],
				[
					'label'	=>	'actions',
					'text'	=>	'Actions'
				]
			],
			'rows' => $this->admin_repository->fetchAll()
		];

	}

	/**
	 * create function
	 *
	 * @param array $data
	 * @return User
	 */
	public function create(array $data): User {

		if(isset($data['password'])){
			unset($data['confirm_password']);
    		unset($data['password_confirmation']);
		}

		$data['user_type'] = config('global.user_types.admin');
		
		return $this->user_repository->create($data);

	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @param integer $id
	 * @return User
	 */
	public function update(array $data, int $id): User {
		
		if(!empty($data['password'])){
			unset($data['confirm_password']);
			unset($data['password_confirmation']);
			$data['password'] = Hash::make($data['password']);
		}
		
		return $this->user_repository->update($data, $id);
		
	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return Response|User
	 */
	public function fetchById(int $id) : Response|User {
		
		$admin = $this->admin_repository->fetchById($id);

		if(!$admin){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return $admin;
	}

}