<?php

namespace Tests\Feature;

use App\Helpers\General;
use App\Models\Company;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class CompanySettingsLogoControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;
	
	public function test_to_see_if_it_fetches_no_company_logo_successfully_without_adding_logo() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();
		Storage::fake('public');

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-logo?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEmpty($json['url']);

	}

	public function test_to_see_if_it_fails_to_upload_logo_with_invalid_file_ext_mime_1() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		Storage::fake('public');
		$file = UploadedFile::fake()->create('document.pdf', 1000);

		$response = $this->post('/api/manage-company-settings-logo', [
			'logo' 			=> $file,
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertArrayHasKey('logo', $json['errors']);
		$this->assertEquals(2, (int) count($json['errors']['logo']));

	}

	public function test_to_see_if_it_fails_to_upload_logo_with_invalid_file_ext_mime_2() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		Storage::fake('public');
		$file = UploadedFile::fake()->create('malicious.php', 1000);

		$response = $this->post('/api/manage-company-settings-logo', [
			'logo' 			=> $file,
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertArrayHasKey('logo', $json['errors']);
		$this->assertEquals(2, (int) count($json['errors']['logo']));

	}

	
	public function test_to_see_if_it_fails_to_upload_logo_with_invalid_file_size() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		Storage::fake('public');
		$file = UploadedFile::fake()->create('image.jpg', 7000);

		$response = $this->post('/api/manage-company-settings-logo', [
			'logo' 			=> $file,
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertArrayHasKey('logo', $json['errors']);
		$this->assertEquals(1, (int) count($json['errors']['logo']));

	}

	public function test_to_see_if_it_fails_to_upload_logo_with_no_file_sent() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		Storage::fake('public');
		
		$response = $this->post('/api/manage-company-settings-logo', [
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertArrayHasKey('logo', $json['errors']);
		$this->assertEquals(1, (int) count($json['errors']['logo']));

	}

	public function test_to_see_if_it_throws_exception_for_upload_logo_file() : void {
    
		$device = 'device 123';
		$c = $this->set_access($device);
		
		Company::truncate();
		$company_id = $this->createTemporaryCompany();
		
		Storage::shouldReceive('disk')->with('public')->andReturnSelf();
		
		Storage::shouldReceive('deleteDirectory')->andThrow(new Exception('Storage error'));
		
		$file = UploadedFile::fake()->image('logo.jpg', 100, 100);
		
		$response = $this->post('/api/manage-company-settings-logo', [
			'logo'        => $file,
			'company_id'  => $company_id
		], $c['headers']);

		$response->assertStatus((int) config('global.error_code'));

		$json = $response->json();
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('something_went_wrong', $json['validity']);


	}

	public function test_to_see_if_it_saves_company_logo_with_valid_file() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		Storage::fake('public');
		$file = UploadedFile::fake()->create('image.png', 2500);

		$response = $this->post('/api/manage-company-settings-logo', [
			'logo' 			=> $file,
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('upload_success', $json['validity']);

		/* now check for the file location */
		$path = 'logos/'.$company_id;
		$files = Storage::disk('public')->files($path);
		$this->assertCount(1, $files);
		
		/* get filename from db */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-logo?'.$params);

		$response->assertStatus(200);

		$json = $response->json();

		$image_db = explode('/', $json['url']);
		$image_db = $image_db[count($image_db) - 1];
		
		$image_storage = explode('/', $files[0]);
		$image_storage = $image_storage[count($image_storage) - 1];
		
		$this->assertEquals($image_db, $image_storage);

	}

	public function test_to_see_if_it_overwrites_company_logo_with_valid_file() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		Storage::fake('public');
		$file = UploadedFile::fake()->create('image.png', 2500);
		$response = $this->post('/api/manage-company-settings-logo', [
			'logo' 			=> $file,
			'company_id' 	=> $company_id
		], $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('upload_success', $json['validity']);


		Storage::fake('public');
		$file = UploadedFile::fake()->create('image.png', 2500);
		$response = $this->post('/api/manage-company-settings-logo', [
			'logo' 			=> $file,
			'company_id' 	=> $company_id
		], $c['headers']);
		$response->assertStatus(200);
		$json = $response->json();
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('upload_success', $json['validity']);


		/* now check for the file location */
		$path = 'logos/'.$company_id;
		$files = Storage::disk('public')->files($path);
		$this->assertCount(1, $files);
		
		/* get filename from db */
		$params = http_build_query([
			'company_id' 		=> $company_id
		]);

		$response = $this->withHeaders($c['headers'])->get('/api/manage-company-settings-logo?'.$params);

		$response->assertStatus(200);

		$json = $response->json();

		$image_db = explode('/', $json['url']);
		$image_db = $image_db[count($image_db) - 1];
		
		$image_storage = explode('/', $files[0]);
		$image_storage = $image_storage[count($image_storage) - 1];
		
		$this->assertEquals($image_db, $image_storage);

	}

	public function test_to_see_if_it_removes_company_logo_for_settings() : void {
		
		$device = 'device 123';

		$c = $this->set_access($device);

		Company::truncate();

		/* creating company this way instead of using factory for to check for default fields */
		$company_id = $this->createTemporaryCompany();

		Storage::fake('public');
		$file = UploadedFile::fake()->create('image.png', 2500);

		$response = $this->post('/api/manage-company-settings-logo', [
			'logo' 			=> $file,
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('upload_success', $json['validity']);
		
		/* add removal */
		$response = $this->delete('/api/manage-company-settings-logo', [
			'company_id' 	=> $company_id
		], $c['headers']);

		$response->assertStatus(200);
		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('remove_success', $json['validity']);

		$path = 'logos/'.$company_id;
		$files = Storage::disk('public')->files($path);
		$this->assertCount(0, $files);

	}

}
