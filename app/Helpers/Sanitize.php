<?php

	namespace App\Helpers;

	class Sanitize{

		public static function input(String $string){

			return trim(strip_tags(stripslashes($string)));

		}

	}