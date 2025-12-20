<?php

	namespace App\Helpers;

	class Sanitize{

		/**
		 * input function
		 *
		 * @param String|null $string
		 * @return void
		 */
		public static function input(String|null $string){

			if($string === null){
				return '';
			}

			return trim(strip_tags(stripslashes($string)));

		}

	}