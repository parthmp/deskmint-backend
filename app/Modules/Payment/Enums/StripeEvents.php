<?php

namespace App\Modules\Payment\Enums;

enum StripeEvents : string {

	case CHECKOUT_COMPLETED = 'checkout.session.completed';
	case CHECKOUT_COMPLETED_ASYNC = 'checkout.session.async_payment_succeeded';

	case INVOICE_PAID = 'invoice.paid';

	/**
	 * getAll function
	 *
	 * @return array
	 */
	public static function getAll() : array {
		return array_column(self::cases(), 'value');
	}

}