<?php

namespace App\Enums\RecurringInvoices;

enum RecurringInvoiceStatus : int {

    case DRAFT = 1;
    case SENT = 2;
    case ACTIVE = 3;
    case PAUSED = 4;
    case PENDING_APPROVAL = 5;
    case CANCELLED = 6;

    public function label() : string {
        return match($this) {
            self::DRAFT 			=> 'Draft',
            self::SENT 				=> 'Sent',
            self::ACTIVE 			=> 'Active',
            self::PAUSED 			=> 'Paused',
            self::PENDING_APPROVAL 	=> 'Pending approval',
			self::CANCELLED 		=> 'Cancelled'
        };
    }

	public static function getRecurringInvoiceStatusLabel(int $status): string {
    	return self::from($status)->label();
	}
}