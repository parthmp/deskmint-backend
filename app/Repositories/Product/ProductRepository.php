<?php

namespace App\Repositories\Product;

use App\Models\Product;

class ProductRepository{

	/**
	 * searchByName function
	 *
	 * @param integer $company_id
	 * @param string $searched
	 * @param integer $limit
	 * @return array
	 */
	public function searchByName(int $company_id, string $searched, int $limit = 50) : array {

		return Product::select('id', 'product_name', 'description', 'price')->where('company_id', '=', $company_id)->where(function($query) use($searched){
			$query->where('product_name', 'LIKE', '%'.$searched.'%');
		})->orderBy('product_name', 'ASC')->limit($limit)->get()->map(function($product){
			return [
				'text'		=>	$product->product_name,
				'value'		=>	$product->id,
				'data'		=>	[
					'product' => $product
				]
			];
		})->toArray();

	}

	/**
	 * fetchById function
	 *
	 * @param integer $id
	 * @return Product|null
	 */
	public function fetchById(int $id) : ?Product {
		return Product::where('id', '=', $id)->first();
	}

	/**
	 * createEmpty function
	 *
	 * @return Product
	 */
	public function createEmpty() : Product {
		return new Product();
	}

}