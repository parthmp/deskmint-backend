<?php

namespace App\Modules\Payment\Enums;

enum PaymentStatus : int {

    case NOT_APPLIED = 1;
    case PARTIALLY_APPLIED = 2;
    case APPLIED = 3;

    public function label() : string {
        return match($this) {
            self::NOT_APPLIED 				=> 'Not applied',
            self::PARTIALLY_APPLIED 		=> 'Partially applied',
            self::APPLIED 					=> 'Applied'
        };
    }

}