<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{	

	private function CheckLoginAuth($email, $password){

		$user = User::where('email', '=', $email)->first();

		

	}

	public function login(Request $request){
		
		return response([
			'message' => 'that'
		], 422);

	}

}
