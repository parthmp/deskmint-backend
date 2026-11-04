<?php

namespace App\Modules\Payment\Enums;

enum PayPalEvents : string {

	case CHECKOUT_APPROVED = 'CHECKOUT.ORDER.APPROVED';
	case PAYMENT_CAPTURED = 'PAYMENT.CAPTURE.COMPLETED';
	case PAYMENT_PENDING = 'PAYMENT.CAPTURE.PENDING';
	
	case SUBSCRIPTION_ACTIVATED = 'BILLING.SUBSCRIPTION.ACTIVATED';
	case SALE_COMPLETED = 'PAYMENT.SALE.COMPLETED';
	case SUBSCRIPTION_SUSPENDED = 'BILLING.SUBSCRIPTION.SUSPENDED';
	case SUBSCRIPTION_PAYMENT_FAILED = 'BILLING.SUBSCRIPTION.PAYMENT.FAILED';
	
	/**
	 * getAll function
	 *
	 * @return array
	 */
	public static function getAll() : array {
		return array_column(self::cases(), 'value');
	}



}