<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Modules\DataTable\DataTable;
use App\Repositories\Product\ProductRepository;

/**
 * ProductService class
 */
class ProductService{

	public function __construct(private ProductRepository $product_repository, private DataTable $datatable){}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return Product|null
	 */
	public function fetchById(int $id) : ?Product {
		return $this->product_repository->fetchById($id);
	}

	/**
	 * upsert function
	 *
	 * @param array $data
	 * @param Product|null $product
	 * @return boolean
	 */
	public function upsert(array $data, ?Product $product) : bool {

		$product->company_id = $data['company_id'];
		$product->product_name = $data['product_name'];
		$product->price = $data['price'] ? $data['price'] : 0;
		$product->sku = $data['sku'] ? $data['sku'] : '';
		$product->description = $data['description'] ? $data['description'] : '';

		return $product->save();
	}

	/**
	 * createEmpty function
	 *
	 * @return Product
	 */
	public function createEmpty() : Product {
		return $this->product_repository->createEmpty();
	}

	public function fetch(array $data) : array{

		$fields = $this->datatable
		->setVars($data)
		->setModel(Product::class)
		->skipColumns(['deleted_at', 'updated_at'])
		->setDatesColumns(['created_at'])
		->setSearchableColumns(['*'])
		->results();

		$fields->each(function($ele){
			$ele->input_type = ucfirst($ele->input_type);
		});
		
		$table_data = [
			'columns' => [
				[
					'label' => 	'id',
					'text'	=>	'ID#'
				],
				[
					'label' => 	'product_name',
					'text'	=>	'Product name'
				],
				[
					'label'	=>	'price',
					'text'	=>	'Price'
				],
				[
					'label'	=>	'sku',
					'text'	=>	'SKU'
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

}