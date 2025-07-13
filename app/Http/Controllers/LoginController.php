<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{

	public function login(Request $request){

		echo Hash::make('pass123');
		die();

		$user = User::where('id', '=', 1)->first();
		
		/*
		if(Auth::check($user)){
			
		}*/

		$token = $user->createToken('admin_token');
		dd($token);

	}

}
