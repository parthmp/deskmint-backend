<?php

namespace App\Modules\Payment\Enums;

enum StripeEvents : string {

	case CHECKOUT_COMPLETED = 'checkout.session.completed';

	/**
	 * getAll function
	 *
	 * @return array
	 */
	public static function getAll() : array {
		return array_column(self::cases(), 'value');
	}

}