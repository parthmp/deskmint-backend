<?php

	namespace App\Helpers;

	class Sanitize{

		/**
		 * input function
		 *
		 * @param String|null $string
		 * @return String|null
		 */
		public static function input(String|null $string) : String|null {

			if($string === null){
				return null;
			}

			return trim(strip_tags(stripslashes($string)));

		}

	}