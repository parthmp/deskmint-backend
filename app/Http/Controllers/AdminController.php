<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller{
    
	public function index(Request $request){

		$users = User::where('user_type', '=', config('global.user_types.admin'))->orderBy('name', 'asc')->get();

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
			'rows' => $users
		];

	}

	public function store(Request $request){

		$v = Validator::make($request->all(), [
			'name'				=>	'required',
			'email'				=>	'required|email',
			'password'			=>	'required|min:8',
			'confirm_password'	=>	'required|min:8'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$name = Sanitize::input($request->input('name'));
		$email = Sanitize::input($request->input('email'));
		$password = $request->input('password');
		$confirm_password = $request->input('confirm_password');

		if($password !== $confirm_password){
			return response(['message' => 'Password and confirm password do not match', 'validity' => 'passwords_not_matched'], config('global.error_code'));
		}

		$exists = User::where('email', '=', $email)->first();
		if($exists){
			return response(['message' => 'Email address already exists', 'validity' => 'email_exists'], config('global.error_code'));
		}

		$user = new User();
		$user->name = $name;
		$user->email = $email;
		$user->password = Hash::make($password);
		$user->user_type = config('global.user_types.admin');
		$user->save();

		return response(['message' => 'Admin created successfully', 'validity' => 'admin_created'], 200);

	}

	private function findUser(Request $request){

		$admin_id = $request->segment(3);

		$admin = User::where([['id', '=', $admin_id], ['user_type', '=', config('global.user_types.admin')]])->first();

		if(!$admin){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		return $admin;

	}

	public function	show(Request $request){

		$admin = $this->findUser($request);

		return $admin;

	}

	public function	update(Request $request){

		$admin = $this->findUser($request);

		$required_array = [
			'name'		=>		'required',
			'email'		=>		'required|email'
		];

		
		$update_password = false;

		if($request->filled('password') || $request->filled('confirm_password')){
			$required_array['password'] = 'required|min:8';
			$required_array['confirm_password'] = 'required|min:8';
			$update_password = true;
		}

		$v = Validator::make($request->all(), $required_array);

		$name = Sanitize::input($request->input('name'));
		$email = Sanitize::input($request->input('email'));
		$password = $request->input('password');
		$confirm_password = $request->input('confirm_password');


		if($v->fails()){
			return response(['message' => 'PASS: =='.$password.'==', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		if($update_password && $password !== $confirm_password){
			return response(['message' => 'Password and confirm password do not match', 'validity' => 'passwords_not_matched'], config('global.error_code'));
		}
		

		$exists = User::where([['email', '=', $email], ['id', '<>', $admin->id]])->first();
		if($exists){
			return response(['message' => 'Email address already exists', 'validity' => 'email_exists'], config('global.error_code'));
		}

		
		$admin->name = $name;
		$admin->email = $email;
		if($update_password){
			$admin->password = Hash::make($password);
		}
		
		if(!$admin->save()){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
		}

		return response(['message' => 'Admin updated successfully', 'validity' => 'admin_updated'], 200);



	}

}
