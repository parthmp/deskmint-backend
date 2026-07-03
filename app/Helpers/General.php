<?php

namespace App\Helpers;

use App\Models\Company;
use App\Models\Country;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection as SupportCollection;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

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

		public static function generateSearchDateText($datetime){

			$date = $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime);
			
			$components = [
				'year' => $date->year,
				'year_short' => substr($date->year, 2),
				'month' => $date->month,
				'month_padded' => sprintf('%02d', $date->month),
				'month_short' => strtolower($date->format('M')),
				'month_full' => strtolower($date->format('F')),
				'day' => $date->day,
				'day_padded' => sprintf('%02d', $date->day),
				'day_ordinal' => strtolower($date->format('jS')),
				'hour' => $date->format('H'),
				'minute' => $date->format('i'),
				'second' => $date->format('s'),
			];
			
			$combinations = [];
			
			$combinations = array_merge($combinations, [
				$components['year'],
				$components['year_short'],
				$components['month'],
				$components['month_padded'],
				$components['month_short'],
				$components['month_full'],
				$components['day'],
				$components['day_padded'],
				$components['day_ordinal'],
			]);
			
			$combinations = array_merge($combinations, self::generateMonthDayCombinations($components));
			
			$combinations = array_merge($combinations, self::generateDateFormatCombinations($components));
			
			$combinations = array_merge($combinations, [
				"{$components['hour']}:{$components['minute']}",
				"{$components['hour']}:{$components['minute']}:{$components['second']}",
			]);

			$combinations = array_merge($combinations, [
				"{$components['day']}-{$components['month_short']}-{$components['year']} {$components['hour']}:{$components['minute']}:{$components['second']}",
				"{$components['day']}-{$components['month_short']}-{$components['year']} {$components['hour']}:{$components['minute']}",
				"{$components['day']}-{$components['month_short']}-{$components['year']} {$components['hour']}",
				"{$components['day']}-{$components['month_short']}-{$components['year']}",
				"{$components['day']}-{$components['month_short']}",
				"{$components['day_ordinal']}-{$components['month_short']}-{$components['year']} {$components['hour']}:{$components['minute']}:{$components['second']}",
				"{$components['day_ordinal']}-{$components['month_short']}-{$components['year']} {$components['hour']}:{$components['minute']}",
				"{$components['day_ordinal']}-{$components['month_short']}-{$components['year']} {$components['hour']}",
				"{$components['day_ordinal']}-{$components['month_short']}-{$components['year']}",
				"{$components['day_ordinal']}-{$components['month_short']}",
			]);
			
			$combinations = array_merge($combinations, [
				"{$components['month_short']} {$components['year']}",
				"{$components['month_full']} {$components['year']}",
			]);
			
			return strtolower(implode(' ', array_unique($combinations)));

		}

		private static function generateMonthDayCombinations($components){

			$combinations = [];

			$day_formats = [$components['day'], $components['day_padded'], $components['day_ordinal']];
			$month_formats = [$components['month_short'], $components['month_full']];
			
			foreach ($day_formats as $day) {
				foreach ($month_formats as $month) {
					$combinations[] = "{$day} {$month}";
					$combinations[] = "{$month} {$day}";
					$combinations[] = "{$day} {$month} {$components['year']}";
					if($day !== $components['day_ordinal']){
						$combinations[] = "{$month} {$day} {$components['year']}";
					}
				}
			}
			
			return $combinations;
		}

		private static function generateDateFormatCombinations($components){

			$combinations = [];
			
			$day_formats = [$components['day'], $components['day_padded']];
			$month_formats = [$components['month'], $components['month_padded']];
			$separators = ['/', '-'];
			
			foreach ($day_formats as $day) {
				foreach ($month_formats as $month) {
					foreach ($separators as $sep) {
						
						$combinations[] = "{$day}{$sep}{$month}{$sep}{$components['year']}";
						$combinations[] = "{$month}{$sep}{$day}{$sep}{$components['year']}";
						$combinations[] = "{$components['year']}{$sep}{$month}{$sep}{$day}";
						$combinations[] = "{$month}{$sep}{$components['year']}{$sep}{$day}";

					}
				}
			}
			
			return $combinations;
		}

		public static function isValidTime($time){
			$patterns = [
				'/^(?:[01]?\d|2[0-3]):[0-5]\d:[0-5]\d$/',               // 24h H:MM:SS or HH:MM:SS
				'/^(?:[01]?\d|2[0-3]):[0-5]\d$/',                       // 24h H:MM or HH:MM
				'/^(0?[1-9]|1[0-2]):[0-5]\d:[0-5]\d\s*(AM|PM)$/i',     // 12h H:MM:SS AM/PM or HH:MM:SS AM/PM
				'/^(0?[1-9]|1[0-2]):[0-5]\d\s*(AM|PM)$/i'              // 12h H:MM AM/PM or HH:MM AM/PM
			];
			
			foreach($patterns as $pattern){
				if(preg_match($pattern, $time)){
					return true;
				}
			}
			return false;
		}

		public static function convertToStandardTime($time) {
			
			if(!self::isValidTime($time)){
				return '';
			}
			
			$time = trim($time);
			
			
			if(preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)$/i', $time, $matches)){
				$hours = (int)$matches[1];
				$minutes = (int)$matches[2];
				$seconds = isset($matches[3]) ? (int)$matches[3] : 0;
				$ampm = strtoupper($matches[4]);
				
				
				if($ampm === 'AM'){
					if($hours === 12){
						$hours = 0; // 12:xx AM becomes 00:xx
					}
				}else{
					if($hours !== 12){
						$hours += 12; // 1-11 PM becomes 13-23
					}
					// 12:xx PM stays as 12:xx
				}
				
				return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

			}
			
			// It's 24-hour format
			if(preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $matches)){
				$hours = (int)$matches[1];
				$minutes = (int)$matches[2];
				$seconds = isset($matches[3]) ? (int)$matches[3] : 0;
				
				return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
			}
			
			return ''; // Should not reach here if isValidTime worked correctly
		}

		public static function isValidPhoneNumber($phone){
			// Check if string contains only digits and optional + at the beginning
			if(!preg_match('/^\+?\d+$/', $phone)){
				return false;
			}
			
			// If it starts with +, ensure there's at least one digit after it
			if(str_starts_with($phone, '+')){
				return strlen($phone) > 1;
			}
			
			// If no +, just ensure it's not empty (already checked it's all digits)
			return strlen($phone) > 0;
		}

		public static function onlyLettersAndNumbers(string $input) : string{
			return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $input));
		}

		public static function jsonTimeToAmPm($json){
			
			if(!is_string($json)){
				return null;
			}
			
			$time_data = json_decode($json, true);
			
			if(json_last_error() !== JSON_ERROR_NONE || !is_array($time_data)){
				return null;
			}
			
			$hours = isset($time_data['hours']) ? (int)$time_data['hours'] : 0;
			$minutes = isset($time_data['minutes']) ? (int)$time_data['minutes'] : 0;
			$seconds = isset($time_data['seconds']) ? (int)$time_data['seconds'] : 0;
			
			if($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 || $seconds < 0 || $seconds > 59){
				return null;
			}

			$ampm = $hours >= 12 ? 'PM' : 'AM';
			$display_hours = $hours % 12;
			$display_hours = $display_hours === 0 ? 12 : $display_hours;
			
			return sprintf('%02d:%02d %s', $display_hours, $minutes, $ampm);

		}

		public static function replaceWithUnderscores(string $input): string{
			// return strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $input));
			// lowercase
			$str = strtolower($input);

			// replace non-alphanumerics with _
			$str = preg_replace('/[^a-z0-9]/', '_', $str);

			// trim leading/trailing _
			$str = preg_replace('/^_+|_+$/', '', $str);

			// if starts with a digit, prefix with 'c'
			$str = preg_replace('/^(\d)/', 'c$1', $str);

			return $str;
		}

		public static function isValidISODateTime($datetime){

			// JavaScript's toISOString() produces a format like YYYY-MM-DDTHH:mm:ss.sssZ
			// The 'T' separates date and time, 'Z' indicates UTC.
			// The milliseconds part ('.sss') is optional in ISO 8601, but common in JS.
			// We'll try to parse with and without milliseconds for robustness.

			// Format with milliseconds
			$format_with_ms = 'Y-m-d\TH:i:s.v\Z'; 
			// Format without milliseconds (or with only seconds)
			$format_without_ms = 'Y-m-d\TH:i:s\Z';

			// Attempt to create a DateTime object from the string using the format with milliseconds
			$datetime_with_ms = DateTime::createFromFormat($format_with_ms, $datetime);
			$errors_with_ms = DateTime::getLastErrors();

			// If parsing with milliseconds failed or resulted in warnings, try without milliseconds
			if($datetime_with_ms === false || !empty($errors_with_ms['warnings']) || !empty($errors_with_ms['errors'])){
				$datetime_without_ms = DateTime::createFromFormat($format_without_ms, $datetime);
				$errors_without_ms = DateTime::getLastErrors();
				
				// Check if parsing without milliseconds was successful and without errors
				return $datetime_without_ms !== false && empty($errors_without_ms['warnings']) && empty($errors_without_ms['errors']);
			}

			// If parsing with milliseconds was successful and without errors
			return true;
		}

		public static function NormalizeColumnName(string $column):string{
			$column = str_ireplace('_id', '', $column);
			return ucfirst(strtolower(str_ireplace('_', ' ', $column)));
		}

		public static function isMySQLDateTime($date){
			$format = 'Y-m-d H:i:s';
			$d = \DateTime::createFromFormat($format, $date);
			return $d && $d->format($format) === $date;
		}

		public static function fixMonthNames(string $date_string):string{
			$months = ['jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar', 'apr' => 'Apr',
               'may' => 'May', 'jun' => 'Jun', 'jul' => 'Jul', 'aug' => 'Aug',
               'sep' => 'Sep', 'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec'];
    
    		return str_ireplace(array_keys($months), array_values($months), $date_string);
		}

		public static function fetchCoutries() : SupportCollection{

			$countries = Country::orderBy('country_name', 'asc')->get()->map(function($country){
				return [
					'value'	=>	$country->id,
					'text'	=>	$country->country_name,
				];
			});
			
			return $countries;

		}

		public static function fetchDefaultCompanyById(int $company_id) : ?Company {
			$company = Company::where([['id', '=', $company_id], ['default', '=', 1]])->first();
			return $company;
		}

		/**
		 * convertTimezone function
		 *
		 * @param integer $timezone_offset_minutes
		 * @param string $date
		 * @param boolean $dateonly
		 * @return string
		 */
		public static function convertTimezone(int $timezone_offset_minutes, string $date, bool $dateonly = false) : string {
			
			$carbon = Carbon::create($date);

			if($timezone_offset_minutes < 0){
				$carbon = $carbon->subMinutes(abs($timezone_offset_minutes));
			}else{
				$carbon = $carbon->addMinutes(abs($timezone_offset_minutes));
			}

			return ($dateonly) ? $carbon->format('Y-m-d') : $carbon->format('Y-m-d H:i:s');

		}

		/**
		 * formatDateTime function
		 *
		 * @param string $date
		 * @param integer $timezone_offset_minutes
		 * @param boolean $show_time
		 * @param boolean $sql_format
		 * @return string
		 */
		public static function formatDateTime(string $date, int $timezone_offset_minutes = 0, bool $show_time = false, $sql_format = false) : string {
		
			$date_obj = Carbon::parse($date);
			
			if($timezone_offset_minutes < 0){
				$date_obj->subMinutes(abs($timezone_offset_minutes));	
			}else if($timezone_offset_minutes > 0){
				$date_obj->addMinutes(abs($timezone_offset_minutes));	
			}

			if(!$sql_format){
				return $show_time ? $date_obj->format('d-M-Y H:i:s') : $date_obj->format('d-M-Y');
			}

			return $show_time ? $date_obj->format('Y-m-d H:i:s') : $date_obj->format('Y-m-d');

		}

		/**
		 * getPaymentMethodName function
		 *
		 * @param integer $payment_method
		 * @return string|null
		 */
		public static function getPaymentMethodName(int $payment_method) : ?string {

			$payment_method = match((int) $payment_method){
				PAYMENT_CASH 		=> 'Cash',
				PAYMENT_NETBANKING 	=> 'NetBanking',
				PAYMENT_PAYPAL 		=> 'PayPal',
				PAYMENT_STRIPE 		=> 'Stripe',
				default 			=> null
			};

			return $payment_method;

		}

	}