<?php

namespace App\Modules\Payment\Enums;

enum InvoiceStatus : int {

    case PENDING = 1;
    case CANCELLED = 2;
    case PARTIALLY_PAID = 3;
    case PAID = 4;

    public function label() : string {
        return match($this) {
            self::PENDING 			=> 'Pending',
            self::CANCELLED 		=> 'Cancelled',
            self::PARTIALLY_PAID 	=> 'Partially Paid',
            self::PAID 				=> 'Paid'
        };
    }

	public function canBeCancelled() : bool {
        return $this === self::PENDING;
    }

	public static function getInvoiceStatusLabel(int $status): string {
    	return self::from($status)->label();
	}
}