<?php

namespace App\Modules\Payment\Enums;

enum TransactionStatus : int {

    case PENDING = 1;
    case REFUNDED = 2;
    case COMPLETED = 3;
    case VOID = 4;
    
    public function label() : string {
        return match($this) {
            self::PENDING 		=> 'Pending',
            self::REFUNDED 		=> 'Refunded',
            self::COMPLETED 	=> 'Completed',
            self::VOID 			=> 'Void'
        };
    }

	public static function getTransactionStatusLabel(int $status): string {
    	return self::from($status)->label();
	}
}