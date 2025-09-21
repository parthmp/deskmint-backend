<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductsController extends Controller{

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

		//try{

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

		// }catch(Exception $e){

		// }

	}

	public function index(Request $request){

	}

	public function store(Request $request){
		
		return $this->saveOrUpdateProduct($request);
		
	}

	public function show(Request $request){

		//return return $this->saveOrUpdateProduct($request);
	}

	public function update(Request $request, int $id){
		return $id;
		return 'inside update method';
	}

	public function destroy(Request $request){
		
	}
    
}
