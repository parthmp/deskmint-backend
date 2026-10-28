<?php

namespace App\Enums\RecurringInvoices;

enum Frequencies : int {

	case DAILY = 1;
    case WEEKLY = 2;
    case BI_WEEKLY = 3;
    case MONTHLY = 4;
    case THREE_MONTHS = 5;
    case SIX_MONTHS = 6;
    case YEARLY = 7;
    case CUSTOM = 8;
   
	/**
	 * label function
	 *
	 * @return string
	 */
    public function label() : string {
        return match($this) {
            self::DAILY 		=> 'Daily',
            self::WEEKLY 		=> 'Weekly',
            self::BI_WEEKLY 	=> 'Bi-Weekly',
            self::MONTHLY 		=> 'Monthly',
            self::THREE_MONTHS 	=> 'Three months',
            self::SIX_MONTHS 	=> 'Six months',
            self::YEARLY 		=> 'Yearly',
            self::CUSTOM 		=> 'Custom',
        };
    }

	/**
	 * dropdownData function
	 *
	 * @return array
	 */
	public static function dropdownData(): array {

		$all = self::cases();

		$data = [];

		foreach($all as $ele){
			$data[] = [
				'text'	=>	$ele->label(),
				'value'	=>	$ele->value,
			];
		}

		return $data;

    }

	/**
	 * getAllValues function
	 *
	 * @return array
	 */
	public static function getAllValues(): array {
        return array_column(self::cases(), 'value');
    }

	/**
	 * paymentGatewayExists function
	 *
	 * @return boolean
	 */
	public static function exists(int $frequency) : bool {
		return in_array($frequency, self::getAllValues());
	}

	/**
	 * getLabelByValue function
	 *
	 * @param integer $frequency
	 * @return string
	 */
	public static function getLabelByValue(int $frequency) : string {
		return self::from($frequency)->label();
	}

	/**
	 * getForGatewayFromValue function
	 *
	 * @param integer $frequency
	 * @param integer|null $custom_days
	 * @return array
	 */
	public static function getForGatewayFromValue(int $frequency, ?int $custom_days = null) : array {
		
		$freq_array = match(self::from($frequency)) {
            self::DAILY 		=> ['DAY', 1],
            self::WEEKLY 		=> ['WEEK', 1],
            self::BI_WEEKLY 	=> ['WEEK', 2],
            self::MONTHLY 		=> ['MONTH', 1],
            self::THREE_MONTHS 	=> ['MONTH', 3],
            self::SIX_MONTHS 	=> ['MONTH', 6],
            self::YEARLY 		=> ['YEAR', 1],
            self::CUSTOM 		=> ['DAY', $custom_days],
			default				=> []
        };
		
		if(empty($freq_array)){
			return $freq_array;
		}

		return [
			'interval_unit'		=>	$freq_array[0],
			'interval_count'	=>	$freq_array[1],
		];

	}

}