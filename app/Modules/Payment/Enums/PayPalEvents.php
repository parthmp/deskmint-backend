<?php

namespace App\Modules\Payment\Enums;

enum PayPalEvents : string {

	case CHECKOUT_APPROVED = 'CHECKOUT.ORDER.APPROVED';
	case PAYMENT_CAPTURED = 'PAYMENT.CAPTURE.COMPLETED';
	case PAYMENT_PENDING = 'PAYMENT.CAPTURE.PENDING';

	/**
	 * getAll function
	 *
	 * @return array
	 */
	public static function getAll() : array {
		return array_column(self::cases(), 'value');
	}



}