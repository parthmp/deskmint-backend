<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\Admin\CreateAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Models\User;
use App\Services\Admin\AdminService;
use App\Services\DeleteService;
use Doctrine\DBAL\Query\QueryException;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminController extends Controller{

	public function __construct(private AdminService $admin_service, private DeleteService $delete_service){

	}
    
	/**
	 * index function
	 *
	 * @param Request $request
	 * @return Response|array
	 */
	public function index(Request $request) : Response|array {
		return $this->admin_service->fetchIndex();
	}


	public function store(CreateAdminRequest $request): mixed {

		try{
			
			$this->admin_service->create($request->validated());
			return response(['message' => 'Admin created successfully', 'validity' => 'admin_created'], 200);

		}catch(QueryException $e){

			if(str_contains($e->getMessage(), 'Duplicate entry')){
				return response(['message' => 'Email already exists', 'validity' => 'email_exists'], config('global.error_code'));
			}

			return General::wentWrong();

		}catch(Exception $e){

			return General::wentWrong();
			
		}

		
	}

	public function update(UpdateAdminRequest $request, int $id): mixed {
		try {
			$this->admin_service->update($request->validated(), $id);
			return response(['message' => 'Admin updated successfully', 'validity' => 'admin_updated'], 200);
		}catch(ModelNotFoundException $e){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}catch(Exception $e){
			return General::wentWrong();
		}
	}

	/**
	 * show function
	 *
	 * @param Request $request
	 * @param integer $id
	 * @return Response|User
	 */
	public function	show(Request $request, int $id) : Response|User {
		return $this->admin_service->fetch($id);
	}

	public function destroy(Request $request){

		try{

			$response = $this->delete_service->deleteByIds($request, User::class, 'Admin');
			return response($response[0], $response[1]);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
