<?php

namespace Tests\Feature;

use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class EmailSettingsContentControllerTest extends TestCase {

	use RefreshDatabase, SetAccess, DefaultCompany;

	private string $url = '/api/manage-email-settings-content';

	public function test_if_default_data_fetched_for_email_content_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		

		$this->assertEmpty($json['email_content_invoice']);
		$this->assertEmpty($json['email_content_reminder']);
		$this->assertEmpty($json['payment_details']);

	}

	private function fetch_data_for_email_content_settings(array $c, int $company_id) : array{

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);
		
		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$json = $response->json();
		return $json;
	}

	private function save_data_for_email_content_settings(string $email_content_invoice, string $email_content_reminder, string $payment_details, int $company_id, array $c){
		return $this->post($this->url, [
			'company_id'				=>	$company_id,
			'email_content_invoice'		=>	$email_content_invoice,
			'email_content_reminder'	=>	$email_content_reminder,
			'payment_details'			=>	$payment_details
		], $c['headers']);
	}

	public function test_if_it_saves_with_empty_data_for_email_content_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->save_data_for_email_content_settings('', '', '', $company_id, $c);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);
		
		$json = $this->fetch_data_for_email_content_settings($c, $company_id);

		$this->assertEmpty($json['email_content_invoice']);
		$this->assertEmpty($json['email_content_reminder']);
		$this->assertEmpty($json['payment_details']);

	}

	public function test_if_it_saves_with_partial_data_for_email_content_settings_1(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->save_data_for_email_content_settings('one', '', '', $company_id, $c);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);
		
		$json = $this->fetch_data_for_email_content_settings($c, $company_id);

		$this->assertEquals('one', $json['email_content_invoice']);
		$this->assertEmpty($json['email_content_reminder']);
		$this->assertEmpty($json['payment_details']);

	}

	public function test_if_it_saves_with_partial_data_for_email_content_settings_2(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->save_data_for_email_content_settings('one', 'two', '', $company_id, $c);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);
		
		$json = $this->fetch_data_for_email_content_settings($c, $company_id);

		$this->assertEquals('one', $json['email_content_invoice']);
		$this->assertEquals('two', $json['email_content_reminder']);
		$this->assertEmpty($json['payment_details']);

	}

	public function test_if_it_saves_with_all_data_for_email_content_settings_2(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->save_data_for_email_content_settings('one', 'two', 'three', $company_id, $c);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);
		
		$json = $this->fetch_data_for_email_content_settings($c, $company_id);

		$this->assertEquals('one', $json['email_content_invoice']);
		$this->assertEquals('two', $json['email_content_reminder']);
		$this->assertEquals('three', $json['payment_details']);

		/* db tests */

		$q = SettingsSection::where([['type', '=', ESC_EMAIL_CONTENT_TYPE], ['company_id', '=', $company_id]])->first();

		$this->assertNotEmpty($q->settings_json);
		$this->assertJson($q->settings_json);

		$ray = json_decode($q->settings_json, true);

		$this->assertEquals('one', $ray['email_content_invoice']);
		$this->assertEquals('two', $ray['email_content_reminder']);
		$this->assertEquals('three', $ray['payment_details']);


	}

	

}
