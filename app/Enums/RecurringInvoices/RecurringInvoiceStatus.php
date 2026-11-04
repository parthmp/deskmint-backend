<?php

namespace App\Enums\RecurringInvoices;

enum RecurringInvoiceStatus : int {

    case DRAFT = 1;
    case SENT = 2;
    case ACTIVE = 3;
    case PAUSED = 4;
    case PENDING_APPROVAL = 5;
    case CANCELLED = 6;
    case PAYMENT_FAILED = 7;
    case EXPIRED = 8;

    public function label() : string {
        return match($this) {
            self::DRAFT 			=> 'Draft',
            self::SENT 				=> 'Sent',
            self::ACTIVE 			=> 'Active',
            self::PAUSED 			=> 'Paused',
            self::PENDING_APPROVAL 	=> 'Pending approval',
			self::CANCELLED 		=> 'Cancelled',
			self::PAYMENT_FAILED 	=> 'Payment failed',
			self::EXPIRED 			=> 'Expired'
        };
    }

	public static function getRecurringInvoiceStatusLabel(int $status): string {
    	return self::from($status)->label();
	}
}