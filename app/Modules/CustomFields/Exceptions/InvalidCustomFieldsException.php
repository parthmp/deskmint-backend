<?php

namespace App\Modules\CustomFields\Exceptions;

use App\Exceptions\BaseException;

class InvalidCustomFieldsException extends BaseException{

	private int $tab;

	public function __construct(string $message, string $validity = '', int $code = 0, int $tab = 0){
        $this->validity = $validity;
        $this->tab = $tab;
        parent::__construct($message, $validity, $code);
    }

	public function getTab(): string {
        return $this->tab;
    }
   
}
