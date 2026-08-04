<?php

namespace App\Enums\PaymentRequests;

enum PaymentRequestStatus : int {

   	case DRAFT = 1;
    case SENT = 2;
    case CANCELLED = 3;
    case COMPLETED = 4;

    public function label() : string {
        return match($this) {
            self::DRAFT 		=> 'Draft',
            self::SENT 			=> 'Sent',
            self::CANCELLED 	=> 'Cancelled',
            self::COMPLETED 	=> 'Completed'
        };
    }
	
}