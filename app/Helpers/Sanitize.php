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

		/**
		 * recursive function
		 *
		 * @param array $input
		 * @return array
		 */
		public static function recursive(array $input) : array {
			
			$result = [];
			
			foreach($input as $key => $value){
				if(is_array($value)){
					$result[$key] = self::recursive($value);
				}else{
					$result[$key] = Sanitize::input($value);
				}
			}
			
			return $result;
		}

	}