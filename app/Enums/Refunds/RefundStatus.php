<?php

namespace App\Enums\Refunds;

enum RefundStatus : int {

   	case NONE = 1;
    case PARTIALLY_REFUNDED = 2;
    case REFUNDED = 3;
	
	/**
	 * label function
	 *
	 * @return string
	 */
    public function label() : string {
        return match($this) {
            self::NONE 						=> 'None',
            self::PARTIALLY_REFUNDED 		=> 'Partially Refunded',
            self::REFUNDED 					=> 'Refunded'
        };
    }

	/**
	 * getRefundStatusLabel function
	 *
	 * @param integer $status
	 * @return string
	 */
	public static function getRefundStatusLabel(int $status): string {
    	return self::from($status)->label();
	}
	
}