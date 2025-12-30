<?php

namespace Tests\Feature;

use App\Models\SettingsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\DefaultCompany;
use Tests\Traits\SetAccess;

class EmailSettingsSMTPControllerTest extends TestCase{

	use RefreshDatabase, SetAccess, DefaultCompany;

	private string $url = '/api/manage-email-settings-smtp';

	public function test_if_default_data_fetched_for_email_smtp_settings(){

		/* set default env */
		Env::getRepository()->set('MAIL_HOST','something');
		Env::getRepository()->set('MAIL_PORT','123');
		Env::getRepository()->set('MAIL_USERNAME','uname');
		Env::getRepository()->set('MAIL_PASSWORD','pass');
		Env::getRepository()->set('MAIL_FROM_ADDRESS','from@address.com');
		Env::getRepository()->set('MAIL_REPLYTO_ADDRESS','re@address.com');
		Env::getRepository()->set('MAIL_ENCRYPTION','tls');
		Env::getRepository()->set('MAIL_FROM_NAME','Jack');


		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);

		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$response->assertStatus(200);

		$json = $response->json();
		
		$this->assertEquals('something', $json['host']);
		$this->assertEquals('123', $json['port']);
		$this->assertEquals('uname', $json['username']);
		$this->assertEquals('pass', $json['password']);
		$this->assertEquals('from@address.com', $json['mail_from_address']);
		$this->assertEquals('re@address.com', $json['reply_to_address']);
		$this->assertEquals('tls', $json['encryption']);
		$this->assertEquals('Jack', $json['mail_from_name']);
		
	}

	private function fetch_data_for_email_smtp_settings(array $c, int $company_id) : array{

		$params = http_build_query([
			'company_id'	=>		$company_id
		]);
		
		$response = $this->withHeaders($c['headers'])->get($this->url.'?'.$params);

		$json = $response->json();
		return $json;
	}

	// private function save_data_for_email_smtp_settings(array $data, array $c){

	// 	return $this->post($this->url, [
	// 		'company_id'					=>	$data['company_id'],
	// 		'host'							=>	$data['host'],
	// 		'port'							=>	$data['port'],
	// 		'username'						=>	$data['username'],
	// 		'mail_from_address'				=>	$data['mail_from_address'],
	// 		'mail_from_name'				=>	$data['mail_from_name'],
	// 		'reply_to_address'				=>	$data['reply_to_address'],
	// 		'encryption'					=>	$data['encryption'],
	// 		'test_email_address'			=>	$data['test_email_address'],
	// 		'password'						=>	$data['password']

	// 	], $c['headers']);

	// }

	public function test_if_it_fails_to_save_with_invalid_data_for_email_smtp_settings_1(){
		Mail::fake();
		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id'	=>	$company_id
		], $c['headers']);
		
		$json = $response->json();
		
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(8, count($json['errors']));
		
		Mail::assertNothingSent();

	}

	public function test_if_it_fails_to_save_with_invalid_data_for_email_smtp_settings_2(){
		Mail::fake();
		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id'	=>	$company_id,
			'host'			=>	'   ',
			'port'			=>	''
		], $c['headers']);
		
		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(8, count($json['errors']));
		
		Mail::assertNothingSent();
	}

	public function test_if_it_fails_to_save_with_invalid_data_for_email_smtp_settings_3(){
		Mail::fake();
		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id'					=>	$company_id,
			'host'							=>	'some',
			'port'							=>	'123',
			'username'						=>	'123',
			'mail_from_address'				=>	'123',
			'mail_from_name'				=>	'123',
			'reply_to_address'				=>	'123',
			'encryption'					=>	'123',
			'test_email_address'			=>	'123'
		], $c['headers']);
		
		$json = $response->json();
		$this->assertArrayHasKey('message', $json);
		$this->assertArrayHasKey('errors', $json);
		$this->assertEquals(4, count($json['errors']));
		Mail::assertNothingSent();

	}

	public function test_if_it_saves_with_all_data_for_email_smtp_settings(){

		Mail::spy();

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id'					=>	$company_id,
			'host'							=>	'some',
			'port'							=>	'123',
			'username'						=>	'bla',
			'mail_from_address'				=>	'some@thing.com',
			'mail_from_name'				=>	'from',
			'reply_to_address'				=>	're@bla.com',
			'encryption'					=>	'ssl',
			'test_email_address'			=>	'test@email.com',
			'password'						=>	'pass'
		], $c['headers']);
		
		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('mail_sent_saved', $json['validity']);

		Mail::assertSent(\Illuminate\Mail\Mailable::class);
		
		Mail::assertSentCount(1);

		$json = $this->fetch_data_for_email_smtp_settings($c, $company_id);
		
		$this->assertEquals('some', $json['host']);
		$this->assertEquals('123', $json['port']);
		$this->assertEquals('bla', $json['username']);
		$this->assertEquals('pass', $json['password']);
		$this->assertEquals('ssl', $json['encryption']);
		$this->assertEquals('some@thing.com', $json['mail_from_address']);
		$this->assertEquals('from', $json['mail_from_name']);
		$this->assertEquals('re@bla.com', $json['reply_to_address']);
		$this->assertEquals('test@email.com', $json['test_email_address']);

	}


	public function test_if_it_overwrites_with_all_data_for_email_smtp_settings(){

		Mail::spy();

		$c = $this->set_access('whatever');

		$company_id = $this->createTemporaryCompany();

		$response = $this->post($this->url, [
			'company_id'					=>	$company_id,
			'host'							=>	'some',
			'port'							=>	'123',
			'username'						=>	'bla',
			'mail_from_address'				=>	'some@thing.com',
			'mail_from_name'				=>	'from',
			'reply_to_address'				=>	're@bla.com',
			'encryption'					=>	'ssl',
			'test_email_address'			=>	'test@email.com',
			'password'						=>	'pass'
		], $c['headers']);

		$response->assertStatus(200);
		
		$json = $response->json();

		$this->assertArrayHasKey('validity', $json);
		$this->assertEquals('mail_sent_saved', $json['validity']);

		Mail::assertSent(\Illuminate\Mail\Mailable::class);
		
		Mail::assertSentCount(1);

		$json = $this->fetch_data_for_email_smtp_settings($c, $company_id);
		
		$this->assertEquals('some', $json['host']);
		$this->assertEquals('123', $json['port']);
		$this->assertEquals('bla', $json['username']);
		$this->assertEquals('pass', $json['password']);
		$this->assertEquals('ssl', $json['encryption']);
		$this->assertEquals('some@thing.com', $json['mail_from_address']);
		$this->assertEquals('from', $json['mail_from_name']);
		$this->assertEquals('re@bla.com', $json['reply_to_address']);
		$this->assertEquals('test@email.com', $json['test_email_address']);


		$response = $this->post($this->url, [
			'company_id'					=>	$company_id,
			'host'							=>	'some edited',
			'port'							=>	'123',
			'username'						=>	'bla',
			'mail_from_address'				=>	'some@thing.com',
			'mail_from_name'				=>	'from edited',
			'reply_to_address'				=>	're@bla.com',
			'encryption'					=>	'tls',
			'test_email_address'			=>	'test@email.com',
			'password'						=>	'pass'
		], $c['headers']);

		$json = $this->fetch_data_for_email_smtp_settings($c, $company_id);

		$this->assertEquals('some edited', $json['host']);
		$this->assertEquals('123', $json['port']);
		$this->assertEquals('bla', $json['username']);
		$this->assertEquals('pass', $json['password']);
		$this->assertEquals('tls', $json['encryption']);
		$this->assertEquals('some@thing.com', $json['mail_from_address']);
		$this->assertEquals('from edited', $json['mail_from_name']);
		$this->assertEquals('re@bla.com', $json['reply_to_address']);
		$this->assertEquals('test@email.com', $json['test_email_address']);

		/* tests for db */
		$q = SettingsSection::where([['type', '=', ESC_EMAIL_SMTP_TYPE], ['company_id', '=', $company_id]])->first();

		$this->assertNotEmpty($q->settings_json);
		$this->assertJson($q->settings_json);

		$ray = json_decode($q->settings_json, true);
		
		$this->assertEquals('some edited', $ray['host']);
		$this->assertEquals('123', $ray['port']);
		$this->assertEquals('bla', $ray['username']);
		$this->assertEquals('pass', decrypt($ray['password']));
		$this->assertEquals('tls', $ray['encryption']);
		$this->assertEquals('some@thing.com', $ray['mail_from_address']);
		$this->assertEquals('from edited', $ray['mail_from_name']);
		$this->assertEquals('re@bla.com', $ray['reply_to_address']);
		$this->assertEquals('test@email.com', $ray['test_email_address']);

	}

}
