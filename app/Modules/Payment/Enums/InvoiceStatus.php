<?php

namespace App\Modules\Payment\Enums;

enum InvoiceStatus : int {

    case DRAFT = 1;
    case SENT = 2;
    case CANCELLED = 3;
    case PARTIALLY_PAID = 4;
    case PAID = 5;

    public function label() : string {
        return match($this) {
            self::DRAFT 			=> 'Draft',
            self::SENT 				=> 'Sent',
            self::CANCELLED 		=> 'Cancelled',
            self::PARTIALLY_PAID 	=> 'Partially Paid',
            self::PAID 				=> 'Paid'
        };
    }

	public function canBeModified() : bool {
        return $this === self::DRAFT || $this === self::SENT;
    }

	public function canBeUncancelled() : bool {
        return $this === self::CANCELLED;
    }

	public static function getInvoiceStatusLabel(int $status): string {
    	return self::from($status)->label();
	}
}