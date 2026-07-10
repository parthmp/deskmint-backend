<?php

namespace Tests\Feature;

use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class EmailSettingsRemindersControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	private string $url = '/api/manage-email-settings-reminders';

	public function test_if_default_data_fetched_for_email_reminders_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals(3, $json['send_n_times']);
		$this->assertEquals(3, $json['days_gap']);
		
	}

	private function fetch_data_for_email_reminders_settings(array $c, int $company_id) : array{

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);
		
		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$json = $response->json();
		return $json;
	}

	private function save_data_for_email_reminders_settings(int $send_n_times, int $days_gap, int $company_id, array $c){

		return $this->post($this->url, [
			'company_id'		=>	$company_id,
			'send_n_times'		=>	$send_n_times,
			'days_gap'			=>	$days_gap
		], $c['headers']);

	}

	public function test_if_it_saves_with_partial_data_for_email_reminders_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->save_data_for_email_reminders_settings(50, 0, $company_id, $c);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);
		
		$json = $this->fetch_data_for_email_reminders_settings($c, $company_id);
		
		$this->assertEquals(50, $json['send_n_times']);
		$this->assertEquals(0, $json['days_gap']);

	}

	public function test_if_it_saves_with_all_data_for_email_reminders_settings(){

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->save_data_for_email_reminders_settings(50, 60, $company_id, $c);

		$response->assertStatus(200);

		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('saved_success', $json['validity']);
		
		$json = $this->fetch_data_for_email_reminders_settings($c, $company_id);

		$this->assertEquals(50, $json['send_n_times']);
		$this->assertEquals(60, $json['days_gap']);

		/* db tests */

		$q = SettingsSection::where([['type', '=', ESC_EMAIL_REMINDERS_TYPE], ['company_id', '=', $company_id]])->first();

		$this->assertNotEmpty($q->settings_json);
		$this->assertJson($q->settings_json);

		$ray = json_decode($q->settings_json, true);

		$this->assertEquals(50, $ray['send_n_times']);
		$this->assertEquals(60, $ray['days_gap']);

	}

}
