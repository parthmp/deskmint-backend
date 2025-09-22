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
	
	

}
