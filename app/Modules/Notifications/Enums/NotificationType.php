<?php

namespace App\Modules\Notifications\Enums;

enum NotificationType : string {

    case INVOICE_OVERPAID = 'invoice overpaid';
    case INVOICE_CANCELLED_PAID = 'cancelled invoice paid';

    // public function label() : string {
    //     return match($this) {
    //         self::INVOICE_OVERPAID 				=> 'invoice_overpaid',
    //         self::INVOICE_CANCELLED_PAID		=> 'invoice_cancelled_paid'
    //     };
    // }

	// public static function getNotificationLabel(int $type): string {
    // 	return self::from($type)->label();
	// }
}