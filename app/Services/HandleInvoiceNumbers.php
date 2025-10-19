<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class HandleInvoiceNumbers{

	use SettingsDefault;

	private int $company_id;
	private array $settings;
	private int $timezone_offset_minutes;
	private Carbon $user_time;

	public function __construct(int $company_id, array $settings, int $timezone_offset_minutes){
		
		$this->company_id = $company_id;
		$this->settings = $settings;
		$this->timezone_offset_minutes = $timezone_offset_minutes;

		$this->setUserTime();

	}

	/**
	 * getUserTime function
	 *
	 * @return Carbon
	 */
	private function setUserTime() : void {

		if($this->timezone_offset_minutes < 0){
			$this->user_time = Carbon::now()->subMinutes($this->timezone_offset_minutes);
		}else{
			$this->user_time = Carbon::now()->addMinutes($this->timezone_offset_minutes);
		}

	}

	/**
	 * getNeverDateRange function
	 *
	 * @return array
	 */
	private function getNeverDateRange() : array {

		$from_datetime = Carbon::now()->subYears(10);
		$to_datetime = Carbon::now();

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getDailyDateRange function
	 *
	 * @return array
	 */
	private function getDailyDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfDay();
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getWeeklyDateRange function
	 *
	 * @return array
	 */
	private function getWeeklyDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfWeek();
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getTwoWeeksDateRange function
	 *
	 * @return array
	 */
	private function getTwoWeeksDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfWeek()->subWeek();
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getMonthlyDateRange function
	 *
	 * @return array
	 */
	private function getMonthlyDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfMonth();
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getTwoMonthsDateRange function
	 *
	 * @return array
	 */
	private function getTwoMonthsDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfMonth()->subMonth();
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getThreeMonthsDateRange function
	 *
	 * @return array
	 */
	private function getThreeMonthsDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfMonth()->subMonths(2);
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getFourMonthsDateRange function
	 *
	 * @return array
	 */
	private function getFourMonthsDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfMonth()->subMonths(3);
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getSixMonthsDateRange function
	 *
	 * @return array
	 */
	private function getSixMonthsDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfMonth()->subMonths(5);
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * getYearlyDateRange function
	 *
	 * @return array
	 */
	private function getYearlyDateRange() : array {

		$from_datetime = $this->user_time->clone()->startOfYear();
		$to_datetime = $this->user_time;

		return [
			'from' 	=> 	$from_datetime,
			'to'	=>	$to_datetime
		];

	}

	/**
	 * fetchLastPatternMatchedEntry function
	 *
	 * @param Carbon $from_date
	 * @param Carbon $to_date
	 * @return Model|null
	 */
	private function fetchLastPatternMatchedEntry(Carbon $from_date, Carbon $to_date) : Invoice|null {

		$record = Invoice::where([['company_id', '=', $this->company_id], ['pattern_matched', '=', 1], ['created_at', '>=', $from_date], ['created_at', '<=', $to_date]])->orderBy('id', 'desc')->first();

		return $record;

	}

	/**
	 * incrementInvoiceNumber function
	 *
	 * @param Invoice $invoice
	 * @param string $padding
	 * @return string
	 */
	private function incrementInvoiceNumber(Invoice $invoice, string $padding) : string {
		/* check if invoice number overflows from 001 to 1000 */
		$scan = (int) $invoice->scan_chars;
		
		$filtered_num = (int) substr((string) $invoice->invoice_number, $scan * -1);

		$filtered_num++;

		$padding_length = strlen($padding);

		return str_pad((string) $filtered_num, $padding_length, '0', STR_PAD_LEFT);

	}

	/**
	 * parsePattern function
	 *
	 * @return string
	 */
	private function parsePattern() : string{

		$year = $this->user_time->clone()->year;
		$day_number = $this->user_time->clone()->format('d');
		$day_name = $this->user_time->clone()->dayName;
		$month_number = $this->user_time->clone()->format('m');
		$month_short_name = $this->user_time->clone()->format('M');
		$month_full_name = $this->user_time->clone()->format('F');

		$pattern_string = $this->settings['number_pattern'];

		$components = [
			'{$year}' => $year,
			'{$day_number}' => $day_number,
			'{$day_name}' => $day_name,
			'{$month_number}' => $month_number,
			'{$month_short_name}' => $month_short_name,
			'{$month_full_name}' => $month_full_name,
		];

		$parsed = str_ireplace(array_keys($components), array_values($components), $pattern_string);

		return $parsed;

	}

	/**
	 * getNextInvoiceNumber function
	 *
	 * @return string
	 */
	public function getNextInvoiceNumber() : string{

		$reset_counter = trim(strtolower($this->settings['reset_counter']));

		$date_range = match($reset_counter){
			'never'			=>	$this->getNeverDateRange(),
			'daily'			=>	$this->getDailyDateRange(),
			'weekly'		=>	$this->getWeeklyDateRange(),
			'two_weeks'		=>	$this->getTwoWeeksDateRange(),
			'monthly'		=>	$this->getMonthlyDateRange(),
			'two_months'	=>	$this->getTwoMonthsDateRange(),
			'three_months'	=>	$this->getThreeMonthsDateRange(),
			'four_months'	=>	$this->getFourMonthsDateRange(),
			'six_months'	=>	$this->getSixMonthsDateRange(),
			'yearly'		=>	$this->getYearlyDateRange(),
		};

		$last_invoice = $this->fetchLastPatternMatchedEntry($date_range['from'], $date_range['to']);

		$increment = $this->settings['number_padding'];

		/* implement for manual reset */
		$manual_reset = SettingsSection::where([['company_id', '=', $this->company_id], ['type', '=', ISC_INVOICE_NUMBER_RESET_TYPE]])->first();
		$manual_reset_settings = $this->getInvoiceResetSettings();
		if($manual_reset){
			$manual_reset_settings = json_decode($manual_reset->settings_json, true);
		}

		$manual_reset = (int) $manual_reset_settings['reset'];

		if($last_invoice && $manual_reset !== 1){
			$increment = $this->incrementInvoiceNumber($last_invoice, $this->settings['number_padding']);
		}
		
		return $this->parsePattern().$increment;
	}



	/* {"reset_counter": "never", "number_padding": "000001", "number_pattern": "{$year}{$day_number}{$day_name}{$month_number}{$month_short_name}{$month_full_name}"} */
	/**
	 * here is what needs to be done
	 * get user's timezone with get request
	 * fetch matched pattern last entry from db - with conditions with reset counter time , day means today from 12 AM to 11:59 PM, week means current week from monday to sunday and so on
	 * increment db invoice number by 1
	 * generate new invoice number by using the set invoice number settings with pattern
	 */

	/**
	 * handle while saving
	 * check if same invoice number added last, if yes increment it by 1 and save it
	 * save scan_chars value, this value must me same as number_padding string length.
	 * match invoice number pattern and detrmine and save if pattern was matched or not.
	 * do not allow any special chars while saving
	 * check if padding overflows for example if padding is set 001 but next invoice number would be 1000, increase scan_chars by 1
	 * override the reset counter to 0 setting while saving
	 */

}