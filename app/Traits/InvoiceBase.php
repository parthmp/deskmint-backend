<?php

namespace App\Traits;

use App\Helpers\Sanitize;
use App\Repositories\Product\ProductRepository;
use Illuminate\Http\Request;

trait InvoiceBase {

	/**
	 * getProductRepository function
	 *
	 * @return ProductRepository
	 */
	private function getProductRepository() : ProductRepository {
        return app(ProductRepository::class);
    }

	/**
	 * filterValidProductRows function
	 *
	 * @param array $product_rows
	 * @param integer $company_id
	 * @return array
	 */
	public function filterValidProductRows(array $product_rows, int $company_id): array {

		if(empty($product_rows)){
			return [];
		}
		
		// Extract all product IDs from request
		$product_ids = [];
		foreach($product_rows as $index => $row){
			if(!empty($row['product_id'])){
				$product_ids[$index] = (int) $row['product_id'];
			}
		}
		
		if(empty($product_ids)){
			return [];
		}
		
		// Check which product IDs exist in database
		$valid_product_ids = $this->getProductRepository()->fetchValidProductIdsByIds($company_id, $product_ids);
		
		// Filter rows - keep only those with valid product IDs
		$filtered_rows = [];
		foreach($product_ids as $index => $product_id){
			if(in_array($product_id, $valid_product_ids, true)){
				$filtered_rows[] = $product_rows[$index];
			}
		}

		$sanitized_rows = [];

		foreach($filtered_rows as $row){
			$temp = [];
			foreach($row as $key => $value){
				$temp[$key] = Sanitize::input($value);
			}
			$sanitized_rows[] = $temp;
		}
		
		return $sanitized_rows;
	}

}