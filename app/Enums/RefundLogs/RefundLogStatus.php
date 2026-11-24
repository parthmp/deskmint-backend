<?php

namespace App\Enums\RefundLogs;

enum RefundLogStatus : int {

   	case PENDING = 1;
    case COMPLETED = 2;

    public function label() : string {
        return match($this) {
            self::PENDING 			=> 'Pending',
            self::COMPLETED 		=> 'Completed'
        };
    }
	
}