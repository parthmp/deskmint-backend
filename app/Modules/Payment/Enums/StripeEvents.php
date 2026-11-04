<?php

namespace App\Modules\Payment\Enums;

enum StripeEvents : string {

	case CHECKOUT_COMPLETED = 'checkout.session.completed';
	case CHECKOUT_COMPLETED_ASYNC = 'checkout.session.async_payment_succeeded';

	case SUBSCRIPTION_PAUSED = 'customer.subscription.paused';
	case SUBSCRIPTION_RESUMED = 'customer.subscription.resumed';
	case SUBSCRIPTION_CANCELLED = 'customer.subscription.deleted';
	case SUBSCRIPTION_PAYMENT_FAILED = 'invoice.payment_failed';

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