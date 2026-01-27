<?php

namespace App\Exceptions;

use Exception;

abstract class BaseException extends Exception{

    protected string $validity = '';
    
    public function __construct(string $message, string $validity = '', int $code = 0){
        $this->validity = $validity;
        parent::__construct($message, $code);
    }
    
    public function getValidity(): string {
        return $this->validity;
    }
}
