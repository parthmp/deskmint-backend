<?php

	namespace App\Helpers;

	use Carbon\Carbon;
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

		public static function isValidTime($time) {
			$patterns = [
				'/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',         // 24h HH:MM:SS
				'/^(?:[01]\d|2[0-3]):[0-5]\d$/',                 // 24h HH:MM
				'/^(0[1-9]|1[0-2]):[0-5]\d\s?(AM|PM)$/i'         // 12h HH:MM AM/PM
			];
			
			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $time)) {
					return true;
				}
			}
			return false;
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

	}