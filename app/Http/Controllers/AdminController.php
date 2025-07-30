<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller{
    
	public function index(Request $request){
		
		/*
		columns : [
						{
							label: 	'index',
							text:	'#'
						},
						{
							label: 	'first_name',
							text:	'First name'
						},
						{
							label: 	'last_name',
							text:	'Last name'
						},
						{
							label: 	'status',
							text:	'Status'
						},
						{
							label: 	'date',
							text:	'Date'
						},
						{
							label: 	'actions',
							text:	'Actions'
						}
					],
					rows: [
						{
							id: 1,
							index: 1,
							first_name: 'Jack1',
							last_name: 'Sparrow',
							status: {
								type:'label',
								text: 'active'
							},
							date: '1950-05-25',
							actions: ['edit', 'delete']
						},]
						]
		*/

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
		$password = Sanitize::input($request->input('password'));
		$confirm_password = Sanitize::input($request->input('confirm_password'));

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

}
