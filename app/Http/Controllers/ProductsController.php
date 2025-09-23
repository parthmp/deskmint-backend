<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Product;
use App\Services\DataTable;
use App\Traits\GeneralDelete;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductsController extends Controller{

	use GeneralDelete;

	private function ifProductExistsById(int $id) : mixed{

		$product = Product::where('id', '=', $id)->first();
		if(!$product){
			return null;
		}

		return $product;
	}

	private function saveOrUpdateProduct(Request $request, int $product_id = 0, bool $add_new = true){

		$v = Validator::make($request->all(), [
			'product_name'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Please fill in required fields', 'validity' => 'invalid_data'], config('global.error_code'));
		}

		if(!$add_new){
			$product = Product::where('id', '=', $product_id)->first();
			if(!$product){
				return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}
		}

		$company_id = Sanitize::input($request->input('company_id'));
		$product_name = Sanitize::input($request->input('product_name'));

		$price = 0;
		$sku = '';
		$description = '';

		if($request->filled('price')){
			$price = Sanitize::input($request->input('price'));
		}

		if($request->filled('sku')){
			$sku = Sanitize::input($request->input('sku'));
		}

		if($request->filled('description')){
			$description = Sanitize::input($request->input('description'));
		}

		if($add_new){
			$product = new Product();
		}

		try{

			$product->company_id = $company_id;
			$product->product_name = $product_name;
			$product->price = $price;
			$product->sku = $sku;
			$product->description = $description;

			if($product->save()){
				$msg = 'Product saved succesfully';
				$validity = 'created_success';
				if(!$add_new){
					$msg = 'Product updated succesfully';
					$validity = 'updated_success';
				}
				return response(['message' => $msg, 'validity' => $validity], 200);
			}

		}catch(Exception $e){

		}

	}

	public function index(Request $request){

		$v = Validator::make($request->all(), [
			'default_per_page'	=>	'required|integer|min:1'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}
		
		$fields = DataTable::sortNPaginate($request, Product::class, ['deleted_at', 'updated_at'], null, ['created_at']);
		
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

	public function store(Request $request){
		
		return $this->saveOrUpdateProduct($request);
		
	}

	public function show(Request $request, int $id){

		$product = $this->ifProductExistsById($id);

		if($product === null){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code')); 
		}

		return $product;

	}

	public function update(Request $request, int $id){
		
		$product = $this->ifProductExistsById($id);
		
		if($product === null){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code')); 
		}

		return $this->saveOrUpdateProduct($request, $id, false);

	}

	public function destroy(Request $request){
		
		try{

			$response = $this->deleteByIds($request, Product::class, 'Product');
			return response($response[0], $response[1]);

		}catch(Exception $e){

			return General::wentWrong();

		}

	}
    
}
