<?php

namespace App\Modules\Payment\Enums;

enum GatewayMode : int {

	case INVOICE = 1;
	case PAYMENT_REQUEST = 2;
	case RECURRING_INVOICE = 3;

	/**
	 * label function
	 *
	 * @return string
	 */
	public function label() : string {
        return match($this) {
            self::INVOICE 				=> 'Invoice',
            self::PAYMENT_REQUEST 		=> 'Payment request',
            self::RECURRING_INVOICE 	=> 'Recurring invoice',
        };
    }

}