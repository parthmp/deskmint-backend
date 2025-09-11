<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Company;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class CompanyControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

    public function test_if_company_does_not_exist(): void{

		$device = 'device 123';

		$c = $this->set_access($device);
		
		$response = $this->post('/api/check-company-exists', [
			
		], $c['headers']);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertArrayHasKey('company_exists', $json);
		$this->assertArrayHasKey('company_id', $json);
		$this->assertFalse($json['company_exists']);
		$this->assertNull($json['company_id']);

    }

	public function test_if_company_exists(): void{

		$device = 'device 123';
		$c = $this->set_access($device);

		$company_id = $this->set_default_company();
		
		$response = $this->post('/api/check-company-exists', [
			
		], $c['headers']);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertArrayHasKey('company_exists', $json);
		$this->assertArrayHasKey('company_id', $json);
		$this->assertTrue($json['company_exists']);
		$this->assertNotNull($json['company_id']);

    }

	public function test_set_default_company_with_invalid_data(): void{

		$device = 'device 123';
		$c = $this->set_access($device);
		
		$response = $this->post('/api/set-default-company', [
		
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

    }


	public function test_set_default_company_with_invalid_data_2(): void{

		$device = 'device 123';

		$c = $this->set_access($device);

		$response = $this->post('/api/set-default-company', [
			'company_name'		=>	''
		], $c['headers']);

		$response->assertStatus((int)config('global.error_code'));
		
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertEquals('invalid_request', $response['validity']);

    }


	public function test_set_default_company_success(): void{

		$device = 'device 123';

		$c = $this->set_access($device);
		
		$response = $this->post('/api/set-default-company', [
			'company_name'		=>	'Bla Company'
		], $c['headers']);

		$response->assertStatus(200);
		
		
		$this->assertArrayHasKey('validity', $response);
		$this->assertArrayHasKey('company_id', $response);
		$this->assertEquals('success', $response['validity']);
		$this->assertNotNull($response['company_id']);
		$this->assertNotEmpty($response['company_id']);

    }

	
}
