<?php

namespace App\Enums\Invoices;

enum ArchivedStatus : int {

   	case NO = 0;
    case YES = 1;
   
    public function label() : string {
        return match($this) {
            self::NO 		=> 'Not archived',
            self::YES 		=> 'Archived'
        };
    }
	
}