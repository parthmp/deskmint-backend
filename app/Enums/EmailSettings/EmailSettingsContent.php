<?php

namespace App\Enums\EmailSettings;

enum EmailSettingsContent : string {

	case INVOICES = 'invoices_email_content';
    case RECURRING_INVOICES = 'recurring_invoices_email_content';
    case PAYMENT_REQUESTS = 'payment_requests_email_content';

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
}