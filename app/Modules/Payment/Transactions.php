<?php

namespace App\Modules\Payment;

class Transactions{

	public function __construct(private DatabaseOperations $database_operations){}

	/**
	 * create function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function create(array $data) : bool {
		return $this->database_operations->insertTransaction($data);
	}

}