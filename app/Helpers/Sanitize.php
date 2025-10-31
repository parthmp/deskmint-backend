<?php

	namespace App\Helpers;

	class Sanitize{

		/**
		 * input function
		 *
		 * @param String $string
		 * @return void
		 */
		public static function input(String $string){

			return trim(strip_tags(stripslashes($string)));

		}

	}