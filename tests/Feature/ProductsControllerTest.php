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

	public function test_if_it_fails_to_fetch_the_product_with_invalid_id() : void{

		$device = 'device 123';

		$company_id = $this->set_default_company();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->getQuery($device, $params, '/api/manage-products/100?');
		$json = $response->json();
		
		$response->assertStatus((int)config('global.error_code'));

		$json = $response->json();

		$this->arrayHasKey('validity', $json);
		$this->assertEquals('invalid_request', $json['validity']);
		
	}

	public function test_if_it_fetchs_the_product_with_valid_id() : void{

		$device = 'device 123';

		$company_id = $this->set_default_company();
		$product = $this->addNewProduct($company_id);

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->getQuery($device, $params, '/api/manage-products/'.$product->id.'?');
		$json = $response->json();
		
		$response->assertStatus(200);

		$json = $response->json();

		$this->arrayHasKey('id', $json);
		$this->arrayHasKey('product_name', $json);
		$this->arrayHasKey('price', $json);
		$this->arrayHasKey('sku', $json);
		$this->arrayHasKey('description', $json);
		
		
	}


	public function test_if_table_loads_products_index(){

	
		$device = 'device 123';

		$company_id = $this->set_default_company();

		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			Product::factory()->create();
		}

		
		$params = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15
		]);

		$response = $this->getQuery($device, $params, '/api/manage-products?');

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_table_filters_for_searched_term_for_product(){

		$device = 'device 123';

		$company_id = $this->set_default_company();

		
		/* add fake data */
		for($z = 0 ; $z < 10 ; $z++){
			Product::factory()->create();
		}

		Product::factory()->create([
			'product_name'	=>	'some abc'
		]);

		
		$params = http_build_query([
			'company_id' 		=> $company_id,
			'default_per_page'	=>	15,
			'searched_term'		=>	'abc'
		]);

		$response = $this->getQuery($device, $params, '/api/manage-products?');

		$response->assertStatus(200);

		$response = $response->json();
		
		$this->assertArrayHasKey('table_data', $response);
		$this->assertArrayHasKey('total_pages', $response);
		$this->assertNotEmpty($response['table_data']['rows']);
		$this->assertNotEmpty($response['table_data']['columns']);
		
	}

	public function test_if_product_delete_fails_with_invalid_data(){
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		$response = $this->delete('/api/manage-products', [
			'ids'			=>	'',
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_ids', $response['validity']);

	}
	
	public function test_if_delete_fails_with_non_numeric_data(){
		
		$device = 'device 123';

		$c = $this->set_access($device);

		$company_id = $this->set_default_company();

		Product::truncate();

		$response = $this->delete('/api/manage-products', [
			'ids'			=>	[
				'bla', 'whatever'
			],
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));

		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('non_numeric', $response['validity']);

	}

	public function test_if_delete_succeeded_with_valid_data(){
		
		$device = 'device 123';

		$c = $this->set_access($device);
		
		$company_id = $this->set_default_company();

		Product::truncate();

		$to_be_deleted_ids = [];
		$to_not_to_be_deleted_ids = [];

		for($z = 0 ; $z < 10 ; $z++){

			$temp_field = Product::factory()->create([
				'id'	=>	$z
			]);

			if($z < 6){
				$to_be_deleted_ids[] = $temp_field->id;
			}else{
				$to_not_to_be_deleted_ids[] = $temp_field->id;
			}

		}
		
		/* delete ids */
		$response = $this->delete('/api/manage-products', [
			'ids'			=>	$to_be_deleted_ids,
			'company_id'	=>	$company_id
		], $c['headers']);

		$response->assertStatus(200);
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('delete_success', $response['validity']);

		/* verify deletion */
		$deleted_ones = Product::whereIn('id', $to_be_deleted_ids)->get();
		$this->assertEmpty($deleted_ones);

		$not_deleted_ones = Product::whereIn('id', $to_not_to_be_deleted_ids)->get();
		$this->assertNotEmpty($not_deleted_ones);

	}


}
