<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\Product\CreateProductRequest;
use App\Models\Product;
use App\Modules\DataTable\Requests\DataTableRequest;
use App\Services\DeleteService;
use App\Services\Product\ProductService;
use Exception;
use Illuminate\Http\Request;

class ProductsController extends Controller{

	public function __construct(private ProductService $product_service, private DeleteService $delete_service){}

	private function ifProductExistsById(int $id){

		$product = $this->product_service->fetchById($id);
		if(!$product){
			return null;
		}

		return $product;
	}

	private function saveOrUpdateProduct(CreateProductRequest $request, int $product_id = 0, bool $add_new = true){

		$data = $request->validated()	;

		if(!$add_new){
			$product = $this->product_service->fetchById($product_id);
			if(!$product){
				return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}
		}

		if($add_new){
			$product = $this->product_service->createEmpty();
		}

		try{

			if($this->product_service->upsert($data, $product)){
				$msg = 'Product saved succesfully';
				$validity = 'created_success';
				if(!$add_new){
					$msg = 'Product updated succesfully';
					$validity = 'updated_success';
				}
				return response(['message' => $msg, 'validity' => $validity], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function index(DataTableRequest $request){

		$data = $request->validated();
		return $this->product_service->fetch($data);

	}

	public function store(CreateProductRequest $request){
		
		return $this->saveOrUpdateProduct($request);
		
	}

	public function show(Request $request, int $id){

		$product = $this->ifProductExistsById($id);

		if($product === null){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code')); 
		}

		return $product;

	}

	public function update(CreateProductRequest $request, int $id){
		
		$product = $this->ifProductExistsById($id);
		
		if($product === null){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code')); 
		}

		return $this->saveOrUpdateProduct($request, $id, false);

	}

	public function destroy(Request $request){
		
		try{

			$response = $this->delete_service->deleteByIds($request, Product::class, 'Product');
			return response($response[0], $response[1]);

		}catch(Exception $e){

			return General::wentWrong();

		}

	}
    
}
