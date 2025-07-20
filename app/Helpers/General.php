<?php

	namespace App\Helpers;

	use Illuminate\Http\Response;

	class General{

		public static function generateRandomString(int $length = 15): string {
			$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
			$charactersLength = strlen($characters);
			$randomString = '';

			for ($i = 0; $i < $length; $i++) {
				$index = random_int(0, $charactersLength - 1);
				$randomString .= $characters[$index];
			}

			return $randomString;

		}

		public static function wentWrong() : Response{
			return response(['message' 	=> 	'Something went wrong', 'validity'	=>	'something_went_wrong'], config('global.error_code'));
		}

	}