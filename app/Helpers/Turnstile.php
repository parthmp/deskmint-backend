<?php

	namespace App\Helpers;

	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Request;


	class Turnstile{

		public static function validate($token, $ip = ''){

			if($ip != ''){
				$use_ip = $ip;
			}else{
				$use_ip = Request::ip();
			}
			

			$response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
				'secret'   	=> env("TURNSTILE_SECRET"),
				'response' 	=> $token,
				'remoteip'	=> $use_ip
			]);

			$data = $response->json();
			
			if($data['success']) {
				return true;
			}else{
				return false;
			}

		}

	}