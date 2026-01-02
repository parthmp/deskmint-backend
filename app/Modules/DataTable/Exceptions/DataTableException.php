<?php

namespace App\Modules\DataTable\Exceptions;

use Exception;

class DataTableException extends Exception{

	/**
	 * invalidModel function
	 *
	 * @param string $model
	 * @return self
	 */
    public static function invalidModel(string $model): self {
        return new self("Invalid model class: {$model}");
    }
    
	/**
	 * invalidJoinConfiguration function
	 *
	 * @param string $message
	 * @return self
	 */
    public static function invalidJoinConfiguration(string $message): self {
        return new self("Invalid join configuration: {$message}");
    }
    
	/**
	 * invalidSortDirection function
	 *
	 * @param string $direction
	 * @return self
	 */
    public static function invalidSortDirection(string $direction): self {
        return new self("Invalid sort direction: {$direction}. Must be 'asc' or 'desc'.");
    }
}