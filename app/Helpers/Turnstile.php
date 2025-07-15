<?php

	namespace App\Helpers;

	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Request;


	class Turnstile{

		public static function validate($token){

			$response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
				'secret'   	=> env("TURNSTILE_SECRET"),
				'response' 	=> $token,
				'remoteip'	=> Request::ip()
			]);

			$data = $response->json();
			
			if($data['success']) {
				return true;
			}else{
				return false;
			}

		}

	}