<?php

namespace App\Enums\RecurringInvoices;

enum RecurringInvoiceStatus : int {

    case DRAFT = 1;
    case SENT = 2;
    case ACTIVE = 3;
    case STOPPED = 4;

    public function label() : string {
        return match($this) {
            self::DRAFT 			=> 'Draft',
            self::SENT 				=> 'Sent',
            self::ACTIVE 			=> 'Active',
            self::STOPPED 			=> 'Stopped'
        };
    }

	public static function getRecurringInvoiceStatusLabel(int $status): string {
    	return self::from($status)->label();
	}
}