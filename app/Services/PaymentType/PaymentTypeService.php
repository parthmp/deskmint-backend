<?php

namespace App\Services\PaymentType;

use App\Models\Payment;
use App\Models\PaymentType;
use App\Modules\DataTable\DataTable;
use App\Repositories\PaymentType\PaymentTypeRepository;

/**
 * PaymentTypeService class
 */
class PaymentTypeService {

	public function __construct(
		private PaymentTypeRepository $payment_type_repository,
		private DataTable $datatable
	){}

	/**
	 * create function
	 *
	 * @param string $payment_type
	 * @return PaymentType
	 */
	public function create(string $payment_type) : PaymentType {
		return $this->payment_type_repository->createOrUpdate($payment_type);
	}

	/**
	 * fetch function
	 *
	 * @param array $data
	 * @return array
	 */
	public function fetch(array $data) : array {

		$fields = $this->datatable
		->setVars($data)
		->setModel(PaymentType::class)
		->skipColumns(['deleted_at', 'updated_at'])
		->setDatesColumns(['created_at'])
		->setSearchableColumns(['*'])
		->results();

		
		$table_data = [
			'columns' => [
				[
					'label' => 	'id',
					'text'	=>	'ID#'
				],
				[
					'label' => 	'payment_type',
					'text'	=>	'Payment type'
				],
				[
					'label'	=>	'created_at',
					'text'	=>	'Added on'
				],
				[
					'label'	=> 'actions',
					'text'	=> 'Actions'
				]
			],
			'rows' => $fields->items()
		];

		$total_pages = $fields->lastPage();

		return [
			'table_data'	=>		$table_data,
			'total_pages'	=>		$total_pages,
			'current_page'	=>		$fields->currentPage()
		];

	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return array
	 */
	public function fetchById(int $id) : array {

		$pt = $this->payment_type_repository->fetchById($id)->toArray();

		unset($pt['updated_at']);
		unset($pt['deleted_at']);

		return $pt;

	}

	/**
	 * update function
	 *
	 * @param string $payment_type
	 * @param integer $id
	 * @return PaymentType
	 */
	public function update(string $payment_type, int $id) : PaymentType {
		return $this->payment_type_repository->createOrUpdate($payment_type, $id);
	}

}