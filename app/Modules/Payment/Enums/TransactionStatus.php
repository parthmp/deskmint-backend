<?php

namespace App\Modules\Payment\Enums;

enum TransactionStatus : int {

    case PENDING = 1;
    case REFUNDED = 2;
    case COMPLETED = 3;
    case VOID = 4;
    case PARTIALLY_REFUNDED = 5;
    
    public function label() : string {
        return match($this) {
            self::PENDING 						=> 'Pending',
            self::REFUNDED 						=> 'Refunded',
            self::COMPLETED 					=> 'Completed',
            self::VOID 							=> 'Void',
            self::PARTIALLY_REFUNDED 			=> 'Partially Refunded'
        };
    }

	public static function getTransactionStatusLabel(int $status): string {
    	return self::from($status)->label();
	}
}