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
}