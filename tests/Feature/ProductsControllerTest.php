<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class ProductsControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	private function getQuery($device, $queryParams, $url = '/api/clients-custom-fields?'){

		$c = $this->set_access($device);

		$response = $this->withHeaders($c['headers'])->get($url . $queryParams);

		return $response;

	}

	private function addNewProduct(int $company_id) : Product{
		
		Product::truncate();

		$product = new Product();
		$product->company_id = $company_id;
		$product->product_name = 'test product here';
		$product->price = 9.98;
		$product->sku = 'some sku';
		$product->description = 'description here';
		$product->save();

		return $product;

	}

	public function test_if_it_fails_to_save_product_with_invalid_data_1() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_if_it_fails_to_save_product_with_invalid_data_2() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'   ',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_if_it_fails_to_save_product_with_invalid_data_3() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-products', [
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_if_it_saves_product_with_valid_data_with_required_data_only() : void{

		Product::truncate();

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'test product',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('created_success', $json['validity']);

		$product = Product::where('company_id', '=', $company_id)->first();
		$this->assertEquals('test product', $product->product_name);

		$this->assertEquals(0, (int)$product->price);
		$this->assertEmpty($product->sku);
		$this->assertEmpty($product->description);
		$this->assertNull($product->deleted_at);

	}

	public function test_if_it_saves_product_with_valid_data_with_partial_data_only() : void{

		Product::truncate();

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'test product',
			'price'						=>	'20.95',
			'description'				=>	'whatever here',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('created_success', $json['validity']);

		$product = Product::where('company_id', '=', $company_id)->first();
		$this->assertEquals('test product', $product->product_name);

		$this->assertEquals(20.95, (float)$product->price);
		$this->assertEmpty($product->sku);
		$this->assertEquals('whatever here', $product->description);
		$this->assertNull($product->deleted_at);

	}

	public function test_if_it_saves_product_with_valid_data_all_data() : void{

		Product::truncate();

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$response = $this->post('/api/manage-products', [
			'product_name'				=>	'test product',
			'price'						=>	'99.95',
			'sku'						=>	'SKU 123',
			'description'				=>	'whatever here',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('created_success', $json['validity']);

		$product = Product::where('company_id', '=', $company_id)->first();
		$this->assertEquals('test product', $product->product_name);

		$this->assertEquals(99.95, (float)$product->price);
		$this->assertEquals('SKU 123', $product->sku);
		$this->assertEquals('whatever here', $product->description);
		$this->assertNull($product->deleted_at);

	}

	public function test_if_it_fails_to_update_product_with_invalid_product_id() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$response = $this->patch('/api/manage-products/100', [
			'product_name'				=>	'',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('invalid_request', $json['validity']);

	}
	

	public function test_if_it_fails_to_update_product_with_invalid_data_1() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$product = $this->addNewProduct($company_id);

		$response = $this->patch('/api/manage-products/'.$product->id, [
			'product_name'				=>	'',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_if_it_fails_to_update_product_with_invalid_data_2() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$product = $this->addNewProduct($company_id);

		$response = $this->patch('/api/manage-products/'.$product->id, [
			'product_name'				=>	'       ',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}

	public function test_if_it_fails_to_update_product_with_invalid_data_3() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$product = $this->addNewProduct($company_id);

		$response = $this->patch('/api/manage-products/'.$product->id, [
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('invalid_data', $json['validity']);

	}
	
	public function test_if_it_updates_product_with_valid_data_with_required_data_only() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$product = $this->addNewProduct($company_id);
		
		$response = $this->patch('/api/manage-products/'.$product->id, [
			'product_name'				=>	'updated product name here',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('updated_success', $json['validity']);

		$updated_product = Product::where('id', '=', $product->id)->first();
		$this->assertEquals('updated product name here', $updated_product->product_name);
		
	}

	public function test_if_it_updates_product_with_valid_data_with_partial_data_only() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$product = $this->addNewProduct($company_id);
		
		$response = $this->patch('/api/manage-products/'.$product->id, [
			'product_name'				=>	'updated product name here',
			'price'						=>	'58.44',
			'sku'						=>	'updated sku here',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('updated_success', $json['validity']);

		$updated_product = Product::where('id', '=', $product->id)->first();
		$this->assertEquals('updated product name here', $updated_product->product_name);
		$this->assertEquals(58.44, $updated_product->price);
		$this->assertEquals('updated sku here', $updated_product->sku);
		
	}

	public function test_if_it_updates_product_with_valid_data_with_all_data() : void{

		$c = $this->set_access('device 123');
		$company_id = $this->set_default_company();

		$product = $this->addNewProduct($company_id);
		
		$response = $this->patch('/api/manage-products/'.$product->id, [
			'product_name'				=>	'updated product name here',
			'price'						=>	'58.44',
			'sku'						=>	'updated sku here',
			'description'				=>	'updated description here',
			'company_id'				=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('updated_success', $json['validity']);

		$updated_product = Product::where('id', '=', $product->id)->first();
		$this->assertEquals('updated product name here', $updated_product->product_name);
		$this->assertEquals(58.44, $updated_product->price);
		$this->assertEquals('updated sku here', $updated_product->sku);
		$this->assertEquals('updated description here', $updated_product->description);
		$this->assertNull($updated_product->deleted_at);
		
	}

}
